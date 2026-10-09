<?php
declare(strict_types=1);

session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$authenticated = !empty($_SESSION['mc_admin_authenticated']);
$dataDir = __DIR__ . '/data';
$storeFile = $dataDir . '/mc_customer_addresses.json';

function customer_address_load(string $file): array
{
    if (!is_file($file)) return [];
    $decoded = json_decode((string) file_get_contents($file), true);
    return is_array($decoded) ? $decoded : [];
}

function customer_address_save(string $file, array $data): bool
{
    $directory = dirname($file);
    if (!is_dir($directory) && !@mkdir($directory, 0755, true)) return false;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    $temporary = $file . '.tmp';
    if (@file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) return false;
    return @rename($temporary, $file);
}

if (!$authenticated) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => '管理画面へログインしてください。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $email = strtolower(trim((string) ($_GET['email'] ?? '')));
    $store = customer_address_load($storeFile);
    if ($email !== '') {
        echo json_encode(['ok' => true, 'customer' => $store[$email] ?? null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        echo json_encode(['ok' => true, 'customers' => $store], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}
$email = strtolower(trim((string) ($body['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => '有効なメールアドレスを入力してください。'], JSON_UNESCAPED_UNICODE);
    exit;
}
$store = customer_address_load($storeFile);
$action = (string) ($body['action'] ?? 'save');
if ($action === 'delete') {
    unset($store[$email]);
} else {
    $address = is_array($body['address'] ?? null) ? $body['address'] : [];
    $store[$email] = [
        'name' => mb_substr(trim((string) ($body['name'] ?? '')), 0, 80),
        'address' => [
            'line1' => mb_substr(trim((string) ($address['line1'] ?? '')), 0, 120),
            'line2' => mb_substr(trim((string) ($address['line2'] ?? '')), 0, 120),
            'city' => mb_substr(trim((string) ($address['city'] ?? '')), 0, 80),
            'state' => mb_substr(trim((string) ($address['state'] ?? '')), 0, 40),
            'zip' => mb_substr(trim((string) ($address['zip'] ?? '')), 0, 20),
            'country' => mb_substr(trim((string) ($address['country'] ?? 'AU')), 0, 60),
            'phone' => mb_substr(trim((string) ($address['phone'] ?? '')), 0, 30),
        ],
        'updated_at' => gmdate('c'),
    ];
}
if (!customer_address_save($storeFile, $store)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => '顧客情報を保存できませんでした。'], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(['ok' => true, 'action' => $action === 'delete' ? 'delete' : 'save']);
