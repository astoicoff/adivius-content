<?php
set_time_limit(120);
require_once __DIR__ . '/helpers.php';
set_headers();

$user    = get_authed_user();
$user_id = $user['id'];
$body    = json_decode(file_get_contents('php://input'), true);
$gen_id  = trim($body['generation_id'] ?? '');
$type    = trim($body['type']          ?? ''); // 'term' | 'question' | 'batch'
$payload = trim($body['payload']       ?? '');
$needed  = max(1, (int)($body['needed'] ?? 1));
$items   = is_array($body['items'] ?? null) ? array_slice($body['items'], 0, 25) : [];

if (!$gen_id || !$type || ($type !== 'batch' && !$payload) || ($type === 'batch' && !$items)) {
    http_response_code(400); echo json_encode(['detail' => 'Missing required fields.']); exit;
}

// Fetch generation
$gen_res  = supabase_call('GET', '/rest/v1/content_generations?id=eq.' . urlencode($gen_id) . '&select=content,model,group_id,user_id');
$gen_data = json_decode($gen_res['body'], true);
if (empty($gen_data)) { http_response_code(404); echo json_encode(['detail' => 'Generation not found.']); exit; }

$gen = $gen_data[0];
if ($gen['user_id'] !== $user_id && !check_group_access($user_id, $gen['group_id'], 'moderator')) {
    http_response_code(403); echo json_encode(['detail' => 'Access denied.']); exit;
}

$full_content = $gen['content'] ?? '';
$settings     = get_user_settings($user_id);
$model        = $gen['model'] ?? 'gpt-5';

// Strip meta lines to get just the HTML body; preserve meta prefix for reconstruction
$parsed    = parse_content_meta($full_content);
$html_body = $parsed['body'];

$all_lines  = explode("\n", $full_content);
$body_start = 0;
$has_meta   = false;
foreach ($all_lines as $i => $line) {
    $trim = trim($line);
    if (!$trim) { if ($has_meta) { $body_start = $i + 1; break; } continue; }
    if (isset($trim[0]) && $trim[0] === '<') { $body_start = $i; break; }
    if (preg_match('/^(h1|title|url)\s*:\s*(.+)$/i', $trim)) { $has_meta = true; $body_start = $i + 1; }
    else break;
}
$meta_prefix = $body_start > 0 ? implode("\n", array_slice($all_lines, 0, $body_start)) . "\n" : '';

if ($type === 'batch') {
    // One coherent revision pass covering every selected checklist item —
    // instead of N sequential full-content rewrites (slow, N× tokens, and
    // each pass risks degrading the prose).
    $term_lines = $head_lines = $q_lines = [];
    foreach ($items as $it) {
        $kind = $it['type'] ?? '';
        if ($kind === 'term') {
            $phrase = trim((string)($it['phrase'] ?? ''));
            $n      = max(1, (int)($it['needed'] ?? 1));
            if ($phrase !== '') $term_lines[] = "- \"{$phrase}\" — add {$n} more natural occurrence(s)";
        } elseif ($kind === 'heading') {
            $phrase = trim((string)($it['phrase'] ?? ''));
            if ($phrase !== '') $head_lines[] = "- \"{$phrase}\"";
        } elseif ($kind === 'question') {
            $txt = trim((string)($it['txt'] ?? ''));
            if ($txt !== '') $q_lines[] = "- {$txt}";
        }
    }
    if (!$term_lines && !$head_lines && !$q_lines) {
        http_response_code(400); echo json_encode(['detail' => 'No valid items.']); exit;
    }

    $system = 'You are an HTML content editor performing a single coherent SEO revision pass on WordPress HTML. '
        . 'Apply ALL requested changes in one edit. Rules: '
        . '(1) BODY TERMS: weave each term into existing sentences the requested number of additional times — rephrase, expand, or replace synonyms. Do not create new headings or sections for terms. '
        . '(2) HEADING PHRASES: incorporate each phrase by REWORKING EXISTING headings only (rename or extend an existing h2/h3). NEVER add new headings or sections for these. If no existing heading fits a phrase, weave it into an existing heading whose topic is closest. '
        . '(3) QUESTIONS: for each question, add one new <h3> section (heading + 1-2 concise paragraphs) at the most contextually fitting location, before any FAQ section if one exists. '
        . '(4) Preserve all other content, structure, and tone. '
        . '(5) Return ONLY the complete modified HTML. No commentary, no markdown fences.';

    $req = '';
    if ($term_lines) $req .= "BODY TERMS to add:\n" . implode("\n", $term_lines) . "\n\n";
    if ($head_lines) $req .= "HEADING PHRASES to work into existing headings:\n" . implode("\n", $head_lines) . "\n\n";
    if ($q_lines)    $req .= "QUESTIONS to answer with a new <h3> section each:\n" . implode("\n", $q_lines) . "\n\n";
    $user_prompt = $req . "Return ONLY the complete modified HTML:\n\n{$html_body}";

} elseif ($type === 'term') {
    $system      = 'You are an HTML content editor. Naturally incorporate a missing SEO term into existing WordPress HTML to reach its target usage count. Rules: (1) Do NOT add new headings or sections. (2) Only modify existing sentences — weave the term in by rephrasing, expanding, or replacing synonyms. (3) Return ONLY the complete modified HTML. No commentary, no markdown fences.';
    $user_prompt = "The content needs the term \"{$payload}\" added {$needed} more time(s) naturally. Incorporate it into existing sentences where it fits contextually.\n\nReturn ONLY the complete modified HTML:\n\n{$html_body}";
} elseif ($type === 'question') {
    $system      = 'You are an HTML content editor. Add a focused section to existing WordPress HTML that directly answers a specific question. Rules: (1) Use an <h3> heading followed by 1–2 concise paragraphs. (2) Insert it at the most contextually appropriate location — before any FAQ section or after the most relevant existing section. (3) Return ONLY the complete modified HTML. No commentary, no markdown fences.';
    $user_prompt = "Add a new section answering this question: \"{$payload}\"\n\nUse an <h3> heading + 1–2 short paragraphs. Return ONLY the complete modified HTML:\n\n{$html_body}";
} else {
    http_response_code(400); echo json_encode(['detail' => 'Invalid type.']); exit;
}

$modified_html    = call_ai($system, $user_prompt, $model, $settings);
$new_full_content = $meta_prefix . $modified_html;

// Snapshot existing content before overwriting
if ($full_content) {
    supabase_call('POST', '/rest/v1/content_generation_versions', [
        'generation_id' => $gen_id,
        'user_id'       => $user_id,
        'content'       => $full_content,
    ]);
}
update_generation_row($gen_id, ['content' => $new_full_content, 'status' => 'completed']);

echo json_encode(['content' => $new_full_content]);
