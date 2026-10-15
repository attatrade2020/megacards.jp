<?php
declare(strict_types=1);

require_once __DIR__ . '/order_core.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') mc_json_response(405, ['error' => 'POST required.']);
mc_rate_limit('paypal_webhook', 120, 3600);
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 1000000) mc_json_response(413, ['error' => 'Request too large.']);

$raw = (string) file_get_contents('php://input');
$event = json_decode($raw, true);
if (!is_array($event)) mc_json_response(400, ['error' => 'Invalid event.']);
$eventType = (string) ($event['event_type'] ?? '');
if ($eventType !== 'INVOICING.INVOICE.PAID') mc_json_response(200, ['ok' => true, 'ignored' => true]);

try {
    $config = mc_private_config();
    $clientId = (string) ($config['paypal_client_id'] ?? '');
    $secret = (string) ($config['paypal_secret'] ?? '');
    $apiBase = rtrim((string) ($config['paypal_api_base'] ?? 'https://api-m.paypal.com'), '/');
    $webhookId = (string) ($config['paypal_webhook_id'] ?? '');
    if ($clientId === '' || $secret === '' || $webhookId === '') throw new RuntimeException('PayPal webhook is not configured.');

    $ch = curl_init($apiBase . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => 'grant_type=client_credentials', CURLOPT_USERPWD => $clientId . ':' . $secret,
        CURLOPT_HTTPHEADER => ['Accept: application/json'], CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_TIMEOUT => 15,
    ]);
    $tokenRaw = curl_exec($ch);
    $tokenCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $tokenData = is_string($tokenRaw) ? json_decode($tokenRaw, true) : [];
    $accessToken = is_array($tokenData) ? (string) ($tokenData['access_token'] ?? '') : '';
    if ($tokenCode >= 400 || $accessToken === '') throw new RuntimeException('PayPal authentication failed.');

    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    $verification = [
        'transmission_id' => (string) ($headers['paypal-transmission-id'] ?? ''),
        'transmission_time' => (string) ($headers['paypal-transmission-time'] ?? ''),
        'cert_url' => (string) ($headers['paypal-cert-url'] ?? ''),
        'auth_algo' => (string) ($headers['paypal-auth-algo'] ?? ''),
        'transmission_sig' => (string) ($headers['paypal-transmission-sig'] ?? ''),
        'webhook_id' => $webhookId,
        'webhook_event' => $event,
    ];
    foreach (['transmission_id','transmission_time','cert_url','auth_algo','transmission_sig'] as $field) {
        if ($verification[$field] === '') mc_json_response(400, ['error' => 'Missing PayPal signature headers.']);
    }
    $certHost = strtolower((string) parse_url($verification['cert_url'], PHP_URL_HOST));
    if (!str_ends_with($certHost, '.paypal.com') && $certHost !== 'paypal.com') mc_json_response(403, ['error' => 'Invalid certificate URL.']);

    $ch = curl_init($apiBase . '/v1/notifications/verify-webhook-signature');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($verification, JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $accessToken],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_TIMEOUT => 20,
    ]);
    $verifyRaw = curl_exec($ch);
    $verifyCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $verifyData = is_string($verifyRaw) ? json_decode($verifyRaw, true) : [];
    if ($verifyCode >= 400 || ($verifyData['verification_status'] ?? '') !== 'SUCCESS') mc_json_response(403, ['error' => 'Webhook signature verification failed.']);

    $invoiceId = trim((string) ($event['resource']['id'] ?? ''));
    $order = mc_find_order_by_paypal_invoice($invoiceId);
    if (!is_array($order)) mc_json_response(200, ['ok' => true, 'ignored' => true]);

    $ch = curl_init($apiBase . '/v2/invoicing/invoices/' . rawurlencode($invoiceId));
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken], CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_TIMEOUT => 20,
    ]);
    $invoiceRaw = curl_exec($ch);
    $invoiceCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $invoice = is_string($invoiceRaw) ? json_decode($invoiceRaw, true) : [];
    if ($invoiceCode >= 400 || !is_array($invoice)) throw new RuntimeException('Could not verify the PayPal invoice.');
    $currency = (string) ($invoice['detail']['currency_code'] ?? $invoice['amount']['currency_code'] ?? '');
    $amount = (int) round((float) ($invoice['amount']['value'] ?? -1));
    $reference = (string) ($invoice['detail']['reference'] ?? '');
    if (($invoice['status'] ?? '') !== 'PAID' || $currency !== 'JPY' || $amount !== (int) ($order['total_jpy'] ?? -2) || $reference !== (string) $order['order_number']) {
        mc_json_response(409, ['error' => 'Invoice payment details do not match the order.']);
    }
    $paid = mc_finalize_order_payment((string) $order['order_number']);
    if (!is_array($paid)) throw new RuntimeException('Paid order stock could not be finalized.');
    mc_json_response(200, ['ok' => true]);
} catch (Throwable $error) {
    error_log('MegaCards PayPal webhook error: ' . $error->getMessage());
    mc_json_response(500, ['error' => 'Webhook processing failed.']);
}
