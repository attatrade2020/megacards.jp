<?php
declare(strict_types=1);

session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

if (empty($_SESSION['mc_admin_authenticated'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => '管理画面へログインしてください。'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$membersFile = __DIR__ . '/data/members.json';
$members = is_file($membersFile) ? json_decode((string) file_get_contents($membersFile), true) : [];
if (!is_array($members)) $members = [];
$customers = [];
foreach ($members as $member) {
    if (!is_array($member)) continue;
    $email = strtolower(trim((string) ($member['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
    $address = is_array($member['address'] ?? null) ? $member['address'] : [];
    $customers[] = [
        'id' => (string) ($member['id'] ?? ''),
        'name' => (string) ($member['name'] ?? ''),
        'email' => $email,
        'phone' => (string) ($member['phone'] ?? ''),
        'country' => (string) ($address['country_code'] ?? 'AU'),
        'address' => [
            'line1' => (string) ($address['line1'] ?? ''),
            'line2' => (string) ($address['line2'] ?? ''),
            'city' => (string) ($address['suburb'] ?? ''),
            'state' => (string) ($address['state'] ?? $member['state'] ?? ''),
            'zip' => (string) ($address['postcode'] ?? ''),
            'country' => (string) ($address['country_code'] ?? 'AU'),
            'phone' => (string) ($member['phone'] ?? ''),
        ],
        'status' => (string) ($member['status'] ?? 'active'),
        'created_at' => (string) ($member['created_at'] ?? ''),
        'updated_at' => (string) ($member['updated_at'] ?? ''),
        'source' => 'member',
    ];
}

echo json_encode(['ok' => true, 'customers' => $customers], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
