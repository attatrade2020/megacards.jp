<?php
declare(strict_types=1);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$adminPassword = 'Kumaiden9';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    echo json_encode(['ok' => true, 'authenticated' => !empty($_SESSION['mc_admin_authenticated'])]);
    exit;
}
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true);
$action = is_array($input) ? (string) ($input['action'] ?? 'login') : 'login';
if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    echo json_encode(['ok' => true]);
    exit;
}

$now = time();
$blockedUntil = (int) ($_SESSION['mc_admin_blocked_until'] ?? 0);
if ($blockedUntil > $now) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'しばらく待ってから再試行してください。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$password = is_array($input) ? (string) ($input['password'] ?? '') : '';
if (!hash_equals($adminPassword, $password)) {
    $attempts = (int) ($_SESSION['mc_admin_attempts'] ?? 0) + 1;
    $_SESSION['mc_admin_attempts'] = $attempts;
    if ($attempts >= 5) {
        $_SESSION['mc_admin_attempts'] = 0;
        $_SESSION['mc_admin_blocked_until'] = $now + 300;
    }
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'パスワードが正しくありません。'], JSON_UNESCAPED_UNICODE);
    exit;
}

session_regenerate_id(true);
$_SESSION['mc_admin_authenticated'] = true;
$_SESSION['mc_admin_attempts'] = 0;
unset($_SESSION['mc_admin_blocked_until']);
echo json_encode(['ok' => true, 'authenticated' => true]);
