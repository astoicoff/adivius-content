<?php
// Page dispatcher — the sole serverless entry point for all HTML pages.
// Each individual page PHP file is require'd from here so only one lambda
// is deployed, keeping the count under Vercel Hobby's 12-function limit.
$path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');

$pages = [
    '/'              => 'dashboard.php',
    '/dashboard'     => 'dashboard.php',
    '/login'         => 'login.php',
    '/new-content'   => 'new-content.php',
    '/new-image'     => 'new-image.php',
    '/images'        => 'images.php',
    '/content-groups'=> 'content-groups.php',
    '/history'       => 'history.php',
    '/api-keys'      => 'api-keys.php',
    '/view-content'  => 'view-content.php',
    '/view-image'    => 'view-image.php',
    '/accept-invite' => 'accept-invite.php',
];

$file = $pages[$path] ?? null;
if ($file) {
    require __DIR__ . '/' . $file;
} else {
    http_response_code(404);
    echo '<!doctype html><html><head><title>404 Not Found</title></head><body><h1>404 Not Found</h1></body></html>';
}
