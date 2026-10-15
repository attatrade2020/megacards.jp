<?php
declare(strict_types=1);
// img_save.php - Download external image and save locally to hide origin URL
require_once __DIR__ . '/order_core.php';
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$url = $input['url'] ?? '';
$filename = $input['filename'] ?? '';

mc_require_same_origin();
if (empty($_SESSION['mc_admin_authenticated'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
if (!preg_match('/(^|\.)snkrdunk\.com$/', $host) && !preg_match('/(^|\.)amazonaws\.com$/', $host)) {
    http_response_code(422);
    echo json_encode(['error' => 'Image host is not allowed']);
    exit;
}

if (!$url || !$filename) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing url or filename']);
    exit;
}

// Sanitize filename
$filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);
if (!$filename) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid filename']);
    exit;
}

// Download image
$ctx = stream_context_create([
    'http' => [
        'timeout' => 15,
        'user_agent' => 'Mozilla/5.0 (compatible; MegaCards/1.0)'
    ]
]);
$imgData = @file_get_contents($url, false, $ctx);

if ($imgData === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to download image']);
    exit;
}

if (strlen($imgData) > 5 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'Image is too large']);
    exit;
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($imgData);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Unsupported image format']);
    exit;
}

// Create directory if needed
$dir = __DIR__ . '/img/products/';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$filepath = $dir . $filename;
$written = file_put_contents($filepath, $imgData);

if ($written === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save image']);
    exit;
}

echo json_encode([
    'success' => true,
    'path' => 'img/products/' . $filename,
    'size' => $written
]);
