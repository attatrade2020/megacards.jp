<?php
declare(strict_types=1);

require_once __DIR__ . '/order_core.php';

$number = trim((string) ($_GET['order'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));
$order = mc_get_order($number, $token);
if (!is_array($order) || !in_array((string) ($order['status'] ?? ''), ['reserved', 'pending_payment'], true)
    || (int) ($order['payment_expires_at'] ?? 0) < time()) {
    http_response_code(410);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment link expired</title><style>body{font:16px Arial;background:#0b1428;color:#fff;display:grid;place-items:center;min-height:100vh;margin:0}.card{max-width:520px;padding:36px;background:#fff;color:#0b1428;border-radius:18px}a{color:#1459e9}</style><div class="card"><h1>Payment link expired</h1><p>This payment link was valid for 10 minutes. Return to MegaCards.jp and place the order again.</p><p><a href="/">Return to the store</a></p></div></html>';
    exit;
}
$url = (string) ($order['provider_payment_url'] ?? '');
if (!preg_match('#^https://(?:www\.)?(?:paypal\.com|wise\.com)/#i', $url)) {
    http_response_code(503);
    echo 'Payment link is not ready.';
    exit;
}
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('Location: ' . $url, true, 302);
exit;
