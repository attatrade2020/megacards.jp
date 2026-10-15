<?php
declare(strict_types=1);

session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

$dataFile = __DIR__ . '/data/shipping_products.json';

function readProfiles(string $path): array {
    if (!is_file($path)) {
        return ['packaging_weight_g' => 250, 'padding_cm' => 4, 'products' => []];
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : ['packaging_weight_g' => 250, 'padding_cm' => 4, 'products' => []];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(readProfiles($dataFile), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

if (empty($_SESSION['mc_admin_authenticated'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Administrator login required.']);
    exit;
}

$action = (string) ($input['action'] ?? 'load_admin');
if ($action === 'load_admin') {
    echo json_encode(['ok' => true, 'data' => readProfiles($dataFile)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action !== 'save') {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

$payload = $input['data'] ?? null;
if (!is_array($payload) || !is_array($payload['products'] ?? null)) {
    http_response_code(422);
    echo json_encode(['error' => 'Product shipping data is required']);
    exit;
}

$clean = [
    'packaging_weight_g' => max(0, min(5000, (int) ($payload['packaging_weight_g'] ?? 250))),
    'padding_cm' => max(0, min(30, (int) ($payload['padding_cm'] ?? 4))),
    'updated_at' => gmdate('c'),
    'products' => [],
];

foreach ($payload['products'] as $id => $profile) {
    if (!is_string($id) || !preg_match('/^[a-z0-9-]{3,80}$/', $id) || !is_array($profile)) {
        continue;
    }
    $clean['products'][$id] = [
        'weight_g' => max(1, min(30000, (int) ($profile['weight_g'] ?? 1))),
        'width_cm' => max(1, min(150, (int) ($profile['width_cm'] ?? 1))),
        'depth_cm' => max(1, min(150, (int) ($profile['depth_cm'] ?? 1))),
        'height_cm' => max(1, min(150, (int) ($profile['height_cm'] ?? 1))),
    ];
}

if (!is_dir(dirname($dataFile)) && !mkdir(dirname($dataFile), 0755, true)) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to create data directory']);
    exit;
}

$json = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$tempFile = $dataFile . '.tmp';
if (file_put_contents($tempFile, $json . PHP_EOL, LOCK_EX) === false || !rename($tempFile, $dataFile)) {
    @unlink($tempFile);
    http_response_code(500);
    echo json_encode(['error' => 'Unable to save shipping data']);
    exit;
}

echo json_encode(['ok' => true, 'updated_at' => $clean['updated_at']]);
