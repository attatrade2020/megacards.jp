<?php
declare(strict_types=1);

require_once __DIR__ . '/order_core.php';

session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') mc_json_response(405, ['error' => 'POST required.']);
mc_require_same_origin();
mc_rate_limit('paypal', 10, 600);
$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) mc_json_response(400, ['error' => 'Invalid request.']);
$number = trim((string) ($input['order_number'] ?? $input['order_id'] ?? ''));
$token = trim((string) ($input['order_token'] ?? ''));
$isAdmin = !empty($_SESSION['mc_admin_authenticated']);
$order = mc_get_order($number, $token, $isAdmin);
if (!is_array($order)) mc_json_response(403, ['error' => 'Order authorization failed.']);
if (($order['payment_method'] ?? '') !== 'paypal') mc_json_response(422, ['error' => 'This order does not use PayPal.']);
if ((int) ($order['payment_expires_at'] ?? 0) < time()) mc_json_response(410, ['error' => 'The payment link has expired.']);
if (!in_array((string) ($order['status'] ?? ''), ['reserved', 'pending_payment'], true)) mc_json_response(409, ['error' => 'This order cannot be invoiced.']);

try {
    $config = mc_private_config();
    $clientId = (string) ($config['paypal_client_id'] ?? '');
    $secret = (string) ($config['paypal_secret'] ?? '');
    $apiBase = (string) ($config['paypal_api_base'] ?? 'https://api-m.paypal.com');
    if ($clientId === '' || $secret === '') throw new RuntimeException('PayPal is not configured.');

    $ch = curl_init($apiBase . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => 'grant_type=client_credentials', CURLOPT_USERPWD => $clientId . ':' . $secret,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: en_AU'], CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_TIMEOUT => 15,
    ]);
    $tokenResponse = curl_exec($ch);
    curl_close($ch);
    $tokenData = is_string($tokenResponse) ? json_decode($tokenResponse, true) : [];
    $paypalToken = is_array($tokenData) ? (string) ($tokenData['access_token'] ?? '') : '';
    if ($paypalToken === '') throw new RuntimeException('PayPal authentication failed.');

    if (!empty($order['paypal_invoice_id']) && !empty($order['provider_payment_url'])) {
        $gated = '/pay.php?order=' . rawurlencode($number) . '&token=' . rawurlencode($token);
        mc_json_response(200, ['ok' => true, 'invoice_id' => $order['paypal_invoice_id'], 'payment_link' => $gated, 'status' => 'existing']);
    }

    $ch = curl_init($apiBase . '/v2/invoicing/generate-next-invoice-number');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => '{}', CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $paypalToken], CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_TIMEOUT => 15]);
    $numberResponse = curl_exec($ch);
    curl_close($ch);
    $numberData = is_string($numberResponse) ? json_decode($numberResponse, true) : [];
    $invoiceNumber = is_array($numberData) ? (string) ($numberData['invoice_number'] ?? ('MC-' . $number)) : ('MC-' . $number);

    $items = [];
    foreach (($order['items'] ?? []) as $item) {
        $items[] = [
            'name' => mb_substr((string) ($item['title'] ?? 'Japanese TCG box'), 0, 200),
            'quantity' => (string) max(1, (int) ($item['qty'] ?? 1)),
            'unit_amount' => ['currency_code' => 'JPY', 'value' => (string) max(1, (int) ($item['unit_price_jpy'] ?? 0))],
        ];
    }
    if ((int) ($order['processing_fee_jpy'] ?? 0) > 0) {
        $items[] = [
            'name' => 'Order Processing Fee',
            'description' => 'Order handling and processing service',
            'quantity' => '1',
            'unit_amount' => ['currency_code' => 'JPY', 'value' => (string) (int) $order['processing_fee_jpy']],
        ];
    }
    $nameParts = preg_split('/\s+/', trim((string) ($order['name'] ?? 'Customer')), 2) ?: ['Customer'];
    $invoice = [
        'detail' => [
            'invoice_number' => $invoiceNumber, 'reference' => $number, 'currency_code' => 'JPY',
            'note' => 'MegaCards.jp order ' . $number . '. Complete payment within 10 minutes.',
            'payment_term' => ['term_type' => 'DUE_ON_RECEIPT'],
        ],
        'invoicer' => ['name' => ['given_name' => 'Atta', 'surname' => 'Trade'], 'email_address' => 'info@atta-jp.com', 'website' => 'https://megacards.jp'],
        'primary_recipients' => [[
            'billing_info' => ['name' => ['given_name' => $nameParts[0] ?? 'Customer', 'surname' => $nameParts[1] ?? ''], 'email_address' => (string) $order['email']],
        ]],
        'items' => $items,
        'configuration' => ['partial_payment' => ['allow_partial_payment' => false], 'allow_tip' => false, 'tax_calculated_after_discount' => false, 'tax_inclusive' => false],
    ];
    $shipping = (int) ($order['shipping']['price_jpy'] ?? 0);
    if ($shipping > 0) $invoice['amount'] = ['breakdown' => ['shipping' => ['amount' => ['currency_code' => 'JPY', 'value' => (string) $shipping]]]];

    $ch = curl_init($apiBase . '/v2/invoicing/invoices');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($invoice, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $paypalToken, 'Prefer: return=representation'],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_TIMEOUT => 20,
    ]);
    $createdRaw = curl_exec($ch);
    $createdCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $created = is_string($createdRaw) ? json_decode($createdRaw, true) : [];
    if ($createdCode >= 400 || !is_array($created) || empty($created['id'])) throw new RuntimeException('PayPal could not create the invoice.');
    $invoiceId = (string) $created['id'];

    $ch = curl_init($apiBase . '/v2/invoicing/invoices/' . rawurlencode($invoiceId) . '/send');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['send_to_invoicer' => false, 'send_to_recipient' => false]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $paypalToken],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_TIMEOUT => 15,
    ]);
    curl_exec($ch);
    curl_close($ch);

    $ch = curl_init($apiBase . '/v2/invoicing/invoices/' . rawurlencode($invoiceId));
    curl_setopt_array($ch, [CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $paypalToken], CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_TIMEOUT => 15]);
    $fetchedRaw = curl_exec($ch);
    curl_close($ch);
    $fetched = is_string($fetchedRaw) ? json_decode($fetchedRaw, true) : [];
    $providerUrl = is_array($fetched) ? (string) ($fetched['detail']['metadata']['recipient_view_url'] ?? '') : '';
    if ($providerUrl === '') $providerUrl = 'https://www.paypal.com/invoice/p/#' . $invoiceId;
    $updated = mc_update_order($number, static function (array $stored) use ($invoiceId, $invoiceNumber, $providerUrl): array {
        $stored['paypal_invoice_id'] = $invoiceId;
        $stored['paypal_invoice_number'] = $invoiceNumber;
        $stored['provider_payment_url'] = $providerUrl;
        $stored['status'] = 'pending_payment';
        return $stored;
    });
    if (!is_array($updated)) throw new RuntimeException('Could not save PayPal invoice details.');
    $gated = '/pay.php?order=' . rawurlencode($number) . '&token=' . rawurlencode($token);
    mc_json_response(201, ['ok' => true, 'invoice_id' => $invoiceId, 'invoice_number' => $invoiceNumber, 'payment_link' => $gated, 'status' => 'created']);
} catch (Throwable $error) {
    error_log('MegaCards PayPal error: ' . $error->getMessage());
    mc_json_response(502, ['error' => 'PayPal could not create the payment link. Please try again.']);
}
