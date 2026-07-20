<?php
// API dispatcher — the sole serverless entry point for /api/*.
// Individual handler files are require'd from here so only one lambda is
// deployed for the entire API surface, keeping the count under Vercel
// Hobby's 12-function limit.

$raw  = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
// Strip /api/ prefix; trim slashes
$path = trim(preg_replace('#^/api/?#', '', $raw), '/');

// Reject empty paths, path traversal, and any characters outside [a-z0-9/_-]
if (!$path || !preg_match('#^[a-zA-Z0-9/_-]+$#', $path)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['detail' => 'Not found.']);
    exit;
}

// Normalize: strip .php extension if the caller included it
$path = preg_replace('/\.php$/i', '', $path);

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
