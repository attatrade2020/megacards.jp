<?php
declare(strict_types=1);

require_once __DIR__ . '/order_core.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') mc_json_response(405, ['error' => 'POST required.']);
mc_require_same_origin();
mc_rate_limit('checkout', 10, 600);
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 30000) mc_json_response(413, ['error' => 'Request is too large.']);
$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) mc_json_response(400, ['error' => 'Invalid checkout request.']);
if (($input['accepted_terms'] ?? false) !== true) mc_json_response(422, ['error' => 'Accept the Terms and Privacy Policy to continue.']);
$quote = mc_verify_payload((string) ($input['quote_token'] ?? ''));
if (!is_array($quote) || ($quote['type'] ?? '') !== 'shipping_quote') mc_json_response(409, ['error' => 'The shipping quote has expired. Calculate shipping again.']);

try {
    $order = mc_create_reserved_order($input, $quote);
    $number = (string) $order['order_number'];
    $accessToken = (string) $order['access_token'];
    $paymentLink = null;
    if (($order['payment_method'] ?? '') === 'wise') {
        $description = $number . ' - Valid for 10 minutes';
        $providerUrl = 'https://wise.com/pay/business/q32cyu?amount=' . rawurlencode((string) $order['total_jpy']) . '&currency=JPY&description=' . rawurlencode($description);
        $order = mc_update_order($number, static function (array $stored) use ($providerUrl): array {
            $stored['provider_payment_url'] = $providerUrl;
            $stored['status'] = 'pending_payment';
            return $stored;
        }) ?? $order;
        $paymentLink = '/pay.php?order=' . rawurlencode($number) . '&token=' . rawurlencode($accessToken);
    }
    $gasPayload = [
        'action' => 'order', 'order_number' => $number, 'name' => $order['name'], 'email' => $order['email'],
        'country' => 'AU', 'contact' => $order['phone'], 'address' => $order['address'],
        'items' => array_map(static fn(array $item): array => [
            'id' => $item['id'], 'title' => $item['title'], 'qty' => $item['qty'], 'price_jpy' => $item['unit_price_jpy'],
        ], $order['items']),
        'subtotal_jpy' => $order['subtotal_jpy'],
        'shipping' => ['carrier' => $order['shipping']['carrier'], 'service' => $order['shipping']['service'], 'price' => $order['shipping']['price_jpy']],
        'payment_method' => $order['payment_method'], 'paypal_fee' => $order['processing_fee_jpy'],
        'total_jpy' => $order['total_jpy'], 'invoice_currency' => 'JPY', 'notes' => 'Validated MegaCards storefront order',
        'timestamp' => $order['created_at'], 'status' => 'Pending_Payment',
    ];
    $gasUrl = (string) (mc_private_config()['gas_url'] ?? '');
    if ($gasUrl === '') throw new RuntimeException('Order administration integration is unavailable.');
    $ch = curl_init($gasUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($gasPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 8, CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $gasResult = curl_exec($ch);
    curl_close($ch);
    if (!is_string($gasResult)) error_log('MegaCards order sync to admin failed for ' . $number);
    mc_json_response(201, [
        'ok' => true,
        'order_number' => $number,
        'order_token' => $accessToken,
        'payment_method' => $order['payment_method'],
        'items' => $order['items'],
        'subtotal_jpy' => $order['subtotal_jpy'],
        'shipping_jpy' => $order['shipping']['price_jpy'],
        'processing_fee_jpy' => $order['processing_fee_jpy'],
        'total_jpy' => $order['total_jpy'],
        'expires_at' => gmdate('c', (int) $order['payment_expires_at']),
        'payment_link' => $paymentLink,
    ]);
} catch (InvalidArgumentException $error) {
    mc_json_response(409, ['error' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('MegaCards checkout error: ' . $error->getMessage());
    mc_json_response(500, ['error' => 'The order could not be created. Please try again.']);
}
