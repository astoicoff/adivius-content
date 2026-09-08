<?php
/**
 * GET /api/nucleus/known-ids — every Nucleus identity this install holds.
 *
 * Roadmap §1.3 (cross-database referential reconciliation). Nucleus cannot put a
 * foreign key on the client_id / site_id values we store — it is a separate
 * database — so instead it asks us what we hold and flags anything it doesn't
 * recognise. Detection instead of constraint.
 *
 * Machine-to-machine: service-token auth, no user session. Mirrors summary.php.
 *
 * Response 200: { "client_ids": [uuid, ...], "site_ids": [uuid, ...] }
 * Both keys always present; either may be an empty array.
 */

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../contracts/hub_contract.php';

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (!$auth && function_exists('getallheaders')) {
    $h    = getallheaders();
    $auth = $h['Authorization'] ?? $h['authorization'] ?? '';
}
header('Content-Type: application/json');
header(HUB_CONTRACT_VERSION_HEADER . ': ' . HUB_CONTRACT_VERSION);

if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)
    || !NUCLEUS_SERVICE_TOKEN
    || !hash_equals(NUCLEUS_SERVICE_TOKEN, $m[1])) {
    http_response_code(401);
    echo json_encode(['detail' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['detail' => 'Method not allowed.']);
    exit;
}

/**
 * Collect distinct non-null uuids from one column of one table.
 * Nucleus ids live on BOTH content_groups (the durable binding, set in the
 * group's Nucleus panel) and content_generations (stamped per piece at handoff),
 * and the two can legitimately differ once a group is re-pointed — so the union
 * is what we actually "hold".
 */
function collect_ids(string $table, string $column): array {
    $res = supabase_call('GET', "/rest/v1/$table?select=$column&$column=not.is.null");
    if ($res['status'] < 200 || $res['status'] >= 300) {
        return [];
    }
    $rows = json_decode($res['body'], true);
    if (!is_array($rows)) {
        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $v = $row[$column] ?? null;
        if (is_string($v) && $v !== '') {
            $out[$v] = true;   // key-dedupe preserves uuid identity exactly
        }
    }
    return array_keys($out);
}

$clientIds = array_values(array_unique(array_merge(
    collect_ids('content_groups', 'client_id'),
    collect_ids('content_generations', 'client_id')
)));

$siteIds = array_values(array_unique(array_merge(
    collect_ids('content_groups', 'site_id'),
    collect_ids('content_generations', 'site_id'),
    collect_ids('content_generations', 'nucleus_resolved_site_id')
)));

sort($clientIds);
sort($siteIds);

// array_values + JSON_FORCE_OBJECT-free encoding keeps these as JSON arrays even
// when empty (the contract requires arrays, never null).
echo json_encode([
    'client_ids' => $clientIds,
    'site_ids'   => $siteIds,
]);
