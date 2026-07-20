<?php
// API dispatcher — the sole serverless entry point for /api/*.
// Individual handler files are require'd from here so only one lambda is
// deployed for the entire API surface, keeping the count under Vercel
// Hobby's 12-function limit.

$raw  = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
// Strip /api/ prefix; trim slashes
$path = trim(preg_replace('#^/api/?#', '', $raw), '/');

// Normalize: strip .php extension before validation so callers using
// either /api/groups or /api/groups.php both resolve correctly
$path = preg_replace('/\.php$/i', '', $path);

// Reject empty paths, path traversal, and unsafe characters
if (!$path || !preg_match('#^[a-zA-Z0-9/_-]+$#', $path)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['detail' => 'Not found.']);
    exit;
}

// Block internal-only files from being invoked as endpoints
if (in_array(basename($path), ['helpers', 'index'], true)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['detail' => 'Not found.']);
    exit;
}

$file = __DIR__ . '/' . $path . '.php';
if (file_exists($file)) {
    require $file;
} else {
    // Use set_headers() so CORS + OPTIONS are handled even on 404
    require_once __DIR__ . '/helpers.php';
    set_headers();
    http_response_code(404);
    echo json_encode(['detail' => 'API endpoint not found.']);
}
