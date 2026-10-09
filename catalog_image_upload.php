<?php
declare(strict_types=1);

session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}
if (empty($_SESSION['mc_admin_authenticated'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Invalid password']);
    exit;
}
if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => '画像ファイルを選択してください。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['image'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => '画像をアップロードできませんでした。'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => '画像は5MB以下にしてください。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file((string) $file['tmp_name']);
$extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($extensions[$mime])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'JPG、PNG、WebP画像を使用してください。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$requested = strtolower(trim((string) ($_POST['product_id'] ?? 'box')));
$slug = preg_replace('/[^a-z0-9]+/', '-', $requested) ?? 'box';
$slug = trim($slug, '-') ?: 'box';
$filename = $slug . '-' . gmdate('YmdHis') . '.' . $extensions[$mime];
$directory = __DIR__ . '/assets/products';
if (!is_dir($directory) && !@mkdir($directory, 0755, true)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => '画像保存先を作成できませんでした。'], JSON_UNESCAPED_UNICODE);
    exit;
}
$target = $directory . '/' . $filename;
if (!@move_uploaded_file((string) $file['tmp_name'], $target)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => '画像を保存できませんでした。'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'path' => 'assets/products/' . $filename], JSON_UNESCAPED_SLASHES);
