<?php
declare(strict_types=1);

require_once __DIR__ . '/order_core.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') mc_json_response(405, ['error' => 'POST required.']);
mc_require_same_origin();
mc_rate_limit('shipping', 20, 600);
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 30000) mc_json_response(413, ['error' => 'Request is too large.']);

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) mc_json_response(400, ['error' => 'Invalid request.']);
$country = strtoupper(trim((string) ($input['country'] ?? 'AU')));
$zip = trim((string) ($input['zip'] ?? ''));
$state = strtoupper(trim((string) ($input['state'] ?? '')));
if ($country !== 'AU' || !preg_match('/^\d{4}$/', $zip) || !in_array($state, ['NSW','VIC','QLD','WA','SA','TAS','ACT','NT'], true)) {
    mc_json_response(422, ['error' => 'Enter a valid Australian postcode and state.']);
}
$cart = mc_clean_cart($input['cart'] ?? []);
if (!$cart) mc_json_response(422, ['error' => 'Add at least one box before calculating shipping.']);

try {
    $available = mc_available_stock_map();
    $reserved = [];
    foreach (mc_catalog_all() as $product) {
        $id = (string) ($product['id'] ?? '');
        if ($id === '') continue;
        $reserved[$id] = max(0, (int) ($product['stock'] ?? 1) - (int) ($available[$id] ?? 0));
    }
    $serverCart = mc_server_cart($cart, $reserved);
} catch (InvalidArgumentException $error) {
    mc_json_response(409, ['error' => $error->getMessage()]);
}

$apiKey = (string) (mc_private_config()['shipandco_api_key'] ?? '');
if ($apiKey === '') mc_json_response(503, ['error' => 'Shipping service is temporarily unavailable.']);

$products = [];
foreach ($serverCart['items'] as $item) {
    $cleanName = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^\x20-\x7E]/', '', (string) $item['title'])));
    if ($cleanName === '') $cleanName = 'Trading Card Game Box';
    $products[] = [
        'name' => mb_substr($cleanName, 0, 60), 'price' => (int) $item['unit_price_jpy'],
        'quantity' => (int) $item['qty'], 'weight' => (int) $item['weight_g'], 'hs_code' => '950440', 'origin' => 'JP',
    ];
}
$parcel = $serverCart['parcel'];
$body = [
    'setup' => ['currency' => 'JPY'],
    'from_address' => [
        'country' => 'JP', 'zip' => '1000001', 'state' => 'Tokyo', 'city' => 'Chiyoda', 'address1' => '1-1',
        'full_name' => 'MegaCards', 'phone' => '03-0000-0000', 'email' => 'info@megacards.jp',
    ],
    'to_address' => [
        'country' => 'AU', 'zip' => $zip, 'state' => $state, 'city' => 'Australia', 'address1' => 'Shipping quote',
        'full_name' => 'MegaCards customer', 'phone' => '+61000000000', 'email' => 'quote@megacards.jp',
    ],
    'products' => $products,
    'parcels' => [[
        'amount' => 1, 'width' => $parcel['width'], 'height' => $parcel['height'], 'depth' => $parcel['depth'], 'weight' => $parcel['weight'],
    ]],
];

$ch = curl_init('https://api.shipandco.com/v1/rates');
curl_setopt_array($ch, [
    CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Access-Token: ' . $apiKey],
    CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => true,
]);
$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);
if (!is_string($response)) mc_json_response(502, ['error' => 'Could not retrieve shipping rates.', 'detail' => $error]);
$data = json_decode($response, true);
if (!is_array($data) || isset($data['code']) || isset($data[0]['errors'])) mc_json_response(502, ['error' => 'No shipping rates are available for this destination.']);

$rates = [];
foreach ($data as $rate) {
    if (!is_array($rate)) continue;
    $carrier = strtolower(trim((string) ($rate['carrier'] ?? '')));
    if (!in_array($carrier, ['dhl', 'fedex'], true)) continue;
    $price = (float) ($rate['price'] ?? 0);
    foreach (($rate['surcharges'] ?? []) as $surcharge) if (is_array($surcharge)) $price += (float) ($surcharge['price'] ?? 0);
    $price = (int) round($price);
    if ($price <= 0) continue;
    $service = trim((string) ($rate['service'] ?? ''));
    $quote = [
        'type' => 'shipping_quote', 'exp' => time() + 900, 'cart_hash' => mc_cart_hash($cart),
        'zip' => $zip, 'state' => $state, 'carrier' => $carrier, 'service' => $service, 'price_jpy' => $price,
    ];
    $rates[] = ['carrier' => $carrier, 'service' => $service, 'price' => $price, 'currency' => 'JPY', 'quote_token' => mc_sign_payload($quote)];
}
if (!$rates) mc_json_response(502, ['error' => 'No DHL or FedEx services are available for this postcode.']);
mc_json_response(200, ['rates' => $rates, 'shipment' => ['weight' => $parcel['weight'], 'width' => $parcel['width'], 'depth' => $parcel['depth'], 'height' => $parcel['height']]]);
