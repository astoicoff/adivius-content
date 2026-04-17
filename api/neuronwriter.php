<?php
require_once __DIR__ . '/helpers.php';
set_headers();
$user    = get_authed_user();
$user_id = $user['id'];
$action  = $_GET['action'] ?? '';

function nw_call($method, $endpoint, $body = null) {
    $ch = curl_init(NEURONWRITER_API_URL . $endpoint);
    $headers = ['X-API-KEY: ' . NEURONWRITER_API_KEY, 'Content-Type: application/json', 'Accept: application/json'];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 90,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => json_decode($response, true)];
}

// GET ?action=projects — list NeuronWriter projects
if ($action === 'projects' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $res = nw_call('GET', '/list-projects');
    if ($res['status'] !== 200) {
        http_response_code(502);
        echo json_encode(['detail' => 'NeuronWriter error: ' . ($res['body']['message'] ?? 'unknown')]);
        exit;
    }
    echo json_encode(['projects' => $res['body']]);
    exit;
}

// POST ?action=set_project — save chosen project ID to user_settings
if ($action === 'set_project' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body       = json_decode(file_get_contents('php://input'), true) ?: [];
    $project_id = trim($body['project_id'] ?? '');
    if (!$project_id) { http_response_code(400); echo json_encode(['detail' => 'project_id required']); exit; }

    $settings = get_user_settings($user_id);
    supabase_call('POST', '/rest/v1/user_settings?on_conflict=user_id', array_merge($settings, [
        'user_id'       => $user_id,
        'nw_project_id' => $project_id,
    ]), ['Prefer: resolution=merge-duplicates,return=minimal']);

    echo json_encode(['success' => true]);
    exit;
}

// POST ?action=score&id={gen_id} — evaluate content, creating query if needed
if ($action === 'score' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $gen_id = $_GET['id'] ?? '';
    if (!$gen_id) { http_response_code(400); echo json_encode(['detail' => 'id required']); exit; }

    // Load user settings for project ID
    $settings   = get_user_settings($user_id);
    $project_id = $settings['nw_project_id'] ?? '';

    if (!$project_id) {
        // Return list of projects for client to pick from
        $res = nw_call('GET', '/list-projects');
        if ($res['status'] !== 200) {
            http_response_code(502);
            echo json_encode(['detail' => 'Failed to fetch NeuronWriter projects.']);
            exit;
        }
        echo json_encode(['needs_project' => true, 'projects' => $res['body']]);
        exit;
    }

    // Load generation
    $gen_res  = supabase_call('GET',
        '/rest/v1/content_generations?id=eq.' . urlencode($gen_id)
        . '&user_id=eq.' . urlencode($user_id)
        . '&select=id,keyword,content,nw_query_id,nw_query_url'
    );
    $gen_data = json_decode($gen_res['body'], true)[0] ?? null;
    if (!$gen_data) { http_response_code(404); echo json_encode(['detail' => 'Generation not found.']); exit; }

    $query_id  = $gen_data['nw_query_id'] ?? '';
    $query_url = $gen_data['nw_query_url'] ?? '';
    $is_new    = false;

    // Create NeuronWriter query if not yet done
    if (!$query_id) {
        $nw_res = nw_call('POST', '/new-query', [
            'project'  => $project_id,
            'keyword'  => $gen_data['keyword'],
            'engine'   => 'google.com',
            'language' => 'English',
        ]);
        if ($nw_res['status'] !== 200 || empty($nw_res['body']['query'])) {
            http_response_code(502);
            echo json_encode(['detail' => 'Failed to create NeuronWriter query: ' . ($nw_res['body']['message'] ?? 'unknown')]);
            exit;
        }
        $query_id  = $nw_res['body']['query'];
        $query_url = $nw_res['body']['query_url'] ?? '';
        $is_new    = true;

        // Store query ID on generation
        supabase_call('PATCH',
            '/rest/v1/content_generations?id=eq.' . urlencode($gen_id) . '&user_id=eq.' . urlencode($user_id),
            ['nw_query_id' => $query_id, 'nw_query_url' => $query_url]
        );

        // Wait for query to be ready (poll up to 90s)
        $ready = false;
        for ($i = 0; $i < 18; $i++) {
            sleep(5);
            $check = nw_call('POST', '/get-query', ['query' => $query_id]);
            if (isset($check['body']['status']) && $check['body']['status'] === 'done') {
                $ready = true;
                break;
            }
        }
        if (!$ready) {
            http_response_code(202);
            echo json_encode(['pending' => true, 'query_id' => $query_id, 'query_url' => $query_url,
                'detail' => 'NeuronWriter query is still processing. Try again in a moment.']);
            exit;
        }
    }

    // Evaluate content
    $html = $gen_data['content'] ?? '';
    $eval = nw_call('POST', '/evaluate-content', [
        'query' => $query_id,
        'html'  => $html,
    ]);
    if ($eval['status'] !== 200 || !isset($eval['body']['content_score'])) {
        http_response_code(502);
        echo json_encode(['detail' => 'Failed to evaluate content: ' . ($eval['body']['message'] ?? 'unknown')]);
        exit;
    }

    echo json_encode([
        'score'     => $eval['body']['content_score'],
        'query_url' => $query_url,
        'is_new'    => $is_new,
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['detail' => 'Unknown action.']);
