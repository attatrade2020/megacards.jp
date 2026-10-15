<?php
declare(strict_types=1);
// soft_delete.php - Logical deletion store for orders/customers
// (workaround for GAS delete_order action that returns success but doesn't persist)
//
// Usage:
//   POST /soft_delete.php?type=order
//     Body: { "action": "delete", "ids": ["ord_1", "ord_2"], "password": "..." }
//   POST /soft_delete.php?type=order
//     Body: { "action": "restore", "ids": ["ord_1"], "password": "..." }
//   GET  /soft_delete.php?type=order
//     -> { "deleted": ["ord_1", "ord_2"] }
//   GET  /soft_delete.php?type=customer
//     -> { "deleted": ["email1@example.com", ...] }
//
// Storage: data/mc_deleted_{type}.json

require_once __DIR__ . '/order_core.php';
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
mc_require_same_origin();
if (empty($_SESSION['mc_admin_authenticated'])) mc_json_response(401, ['error' => 'Administrator login required.']);

$type = $_GET['type'] ?? 'order';
if (!in_array($type, ['order', 'customer'], true)) {
    echo json_encode(['error' => 'invalid type']); exit;
}

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir) && !@mkdir($dataDir, 0755, true)) $dataDir = sys_get_temp_dir();
$STORAGE = $dataDir . '/mc_deleted_' . $type . '.json';

function load_deleted($file) {
    if (!file_exists($file)) return [];
    $raw = @file_get_contents($file);
    $d = json_decode($raw, true);
    return is_array($d) ? array_values(array_unique(array_filter($d))) : [];
}
function save_deleted($file, $list) {
    $list = array_values(array_unique(array_filter($list, fn($x) => is_string($x) && $x !== '')));
    @file_put_contents($file, json_encode($list, JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $deleted = load_deleted($STORAGE);
    echo json_encode(['deleted' => $deleted, 'type' => $type, 'count' => count($deleted)]);
    exit;
}

// POST
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) { echo json_encode(['error' => 'invalid json']); exit; }
$action = $body['action'] ?? '';
$ids    = $body['ids'] ?? [];
if (!is_array($ids)) $ids = [$ids];
$ids = array_values(array_filter($ids, fn($x) => is_string($x) || is_numeric($x)));
$ids = array_map('strval', $ids);

$deleted = load_deleted($STORAGE);

if ($action === 'delete') {
    foreach ($ids as $id) {
        if (!in_array($id, $deleted, true)) $deleted[] = $id;
    }
    save_deleted($STORAGE, $deleted);
    echo json_encode(['ok' => true, 'action' => 'delete', 'added' => count($ids), 'total_deleted' => count($deleted)]);
    exit;
}

if ($action === 'restore') {
    $deleted = array_values(array_filter($deleted, fn($id) => !in_array($id, $ids, true)));
    save_deleted($STORAGE, $deleted);
    echo json_encode(['ok' => true, 'action' => 'restore', 'removed' => count($ids), 'total_deleted' => count($deleted)]);
    exit;
}

if ($action === 'clear') {
    save_deleted($STORAGE, []);
    echo json_encode(['ok' => true, 'action' => 'clear']);
    exit;
}

echo json_encode(['error' => 'unknown action']);
