<?php
declare(strict_types=1);

require_once __DIR__ . '/order_core.php';
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (empty($_SESSION['mc_admin_authenticated'])) mc_json_response(401, ['error' => 'Administrator login required.']);
mc_require_same_origin();
$config = mc_private_config();
$password = (string) ($config['gas_admin_password'] ?? '');
if ($password === '') mc_json_response(503, ['error' => 'Admin integration is not configured.']);
$gasUrl = (string) ($config['gas_url'] ?? '');
if ($gasUrl === '') mc_json_response(503, ['error' => 'Admin integration is not configured.']);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $query = $_GET;
    $query['password'] = $password;
    $url = $gasUrl . '?' . http_build_query($query);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 8, CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => true]);
} elseif ($method === 'POST') {
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) mc_json_response(400, ['error' => 'Invalid request.']);
    $input['password'] = $password;
    $ch = curl_init($gasUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 8, CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => true,
    ]);
} else {
    mc_json_response(405, ['error' => 'Method not allowed.']);
}
$result = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);
if (!is_string($result)) mc_json_response(502, ['error' => 'Admin integration failed.', 'detail' => $error]);
http_response_code($code >= 200 && $code < 600 ? $code : 200);
echo $result;
