<?php
declare(strict_types=1);

require_once __DIR__ . '/order_core.php';

const MEGACARDS_ORDER_SESSION_TTL = 2592000;
ini_set('session.gc_maxlifetime', (string) MEGACARDS_ORDER_SESSION_TTL);
session_set_cookie_params([
    'lifetime' => MEGACARDS_ORDER_SESSION_TTL,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$sessionMember = $_SESSION['mc_member'] ?? null;
if (!is_array($sessionMember) || empty($sessionMember['id']) || empty($sessionMember['email'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Please log in to view your orders.']);
    exit;
}

$ordersFile = __DIR__ . '/data/member_orders.json';
$statusFile = __DIR__ . '/data/mc_order_status.json';

function member_orders_read(string $path): array
{
    if (!is_file($path)) return [];
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function member_orders_origin_allowed(): bool
{
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin === '') return true;
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
    return $originHost !== '' && hash_equals($host, $originHost);
}

function member_orders_clean_items($items): array
{
    if (!is_array($items)) return [];
    $clean = [];
    foreach (array_slice($items, 0, 50) as $item) {
        if (!is_array($item)) continue;
        $title = trim((string) ($item['title'] ?? $item['name'] ?? ''));
        if ($title === '') continue;
        $clean[] = [
            'id' => substr(trim((string) ($item['id'] ?? '')), 0, 100),
            'title' => mb_substr($title, 0, 180),
            'qty' => max(1, min(99, (int) ($item['qty'] ?? $item['quantity'] ?? 1))),
            'price_jpy' => max(0, (int) ($item['price_jpy'] ?? $item['price'] ?? 0)),
        ];
    }
    return $clean;
}

function member_orders_clean_address($address): array
{
    if (!is_array($address)) $address = [];
    return [
        'line1' => mb_substr(trim((string) ($address['line1'] ?? '')), 0, 120),
        'line2' => mb_substr(trim((string) ($address['line2'] ?? '')), 0, 120),
        'city' => mb_substr(trim((string) ($address['city'] ?? $address['suburb'] ?? '')), 0, 80),
        'state' => substr(strtoupper(trim((string) ($address['state'] ?? ''))), 0, 4),
        'zip' => substr(trim((string) ($address['zip'] ?? $address['postcode'] ?? '')), 0, 10),
        'country' => 'Australia',
        'phone' => mb_substr(trim((string) ($address['phone'] ?? '')), 0, 30),
    ];
}

function member_orders_for_member(array $member, string $ordersFile, string $statusFile): array
{
    $memberId = (string) $member['id'];
    $orders = [];
    foreach (member_orders_read($ordersFile) as $orderNumber => $order) {
        if (!is_array($order) || (string) ($order['member_id'] ?? '') !== $memberId) continue;
        $number = (string) ($order['order_number'] ?? $orderNumber);
        if ($number !== '') $orders[$number] = $order;
    }

    $statuses = member_orders_read($statusFile);
    foreach ($orders as $number => &$order) {
        if (isset($statuses[$number]) && is_array($statuses[$number])) {
            $status = trim((string) ($statuses[$number]['status'] ?? ''));
            $tracking = trim((string) ($statuses[$number]['tracking'] ?? ''));
            if ($status !== '') $order['status'] = str_replace('_', ' ', $status);
            if ($tracking !== '') $order['tracking_number'] = $tracking;
        }
    }
    unset($order);
    uasort($orders, static fn(array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
    return array_values($orders);
}

function member_orders_json(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function member_orders_pdf_text(string $value): string
{
    $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
    if ($converted === false) $converted = preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';
    $converted = preg_replace('/[\x00-\x1F\x7F]/', ' ', $converted) ?? '';
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $converted);
}

function member_orders_pdf(array $order): string
{
    $commands = [];
    $add = static function (string $text, float $x, float $y, int $size = 10, string $font = 'F1') use (&$commands): void {
        $commands[] = sprintf('BT /%s %d Tf 1 0 0 1 %.1F %.1F Tm (%s) Tj ET', $font, $size, $x, $y, member_orders_pdf_text($text));
    };
    $add('MegaCards.jp', 48, 793, 22, 'F2');
    $add('ORDER RECEIPT', 48, 766, 11, 'F2');
    $number = (string) ($order['order_number'] ?? '');
    $created = (string) ($order['created_at'] ?? '');
    $createdTs = strtotime($created);
    $add('Receipt / Order no.: ' . $number, 48, 735, 10, 'F2');
    $add('Issued: ' . ($createdTs ? date('j M Y, H:i T', $createdTs) : $created), 48, 718);
    $add('Status: ' . (string) ($order['status'] ?? 'Pending payment'), 48, 701);
    $add('Payment method: ' . strtoupper((string) ($order['payment_method'] ?? '')), 48, 684);

    $add('CUSTOMER', 48, 651, 10, 'F2');
    $add((string) ($order['customer_name'] ?? ''), 48, 634);
    $add((string) ($order['email'] ?? ''), 48, 617);
    $address = is_array($order['address'] ?? null) ? $order['address'] : [];
    $addressLines = array_filter([
        (string) ($address['line1'] ?? ''),
        (string) ($address['line2'] ?? ''),
        trim((string) ($address['city'] ?? '') . ', ' . (string) ($address['state'] ?? '') . ' ' . (string) ($address['zip'] ?? '')),
        'Australia',
        !empty($address['phone']) ? 'Tel: ' . (string) $address['phone'] : '',
    ], static fn(string $line): bool => trim($line, " ,") !== '');
    $y = 600;
    foreach ($addressLines as $line) { $add($line, 48, $y); $y -= 16; }

    $y -= 18;
    $add('ITEMS', 48, $y, 10, 'F2');
    $add('Qty', 350, $y, 10, 'F2');
    $add('Unit', 405, $y, 10, 'F2');
    $add('Line total', 485, $y, 10, 'F2');
    $y -= 20;
    foreach ((array) ($order['items'] ?? []) as $item) {
        if ($y < 185) break;
        $title = (string) ($item['title'] ?? 'Item');
        if (mb_strlen($title) > 52) $title = mb_substr($title, 0, 49) . '...';
        $qty = max(1, (int) ($item['qty'] ?? 1));
        $unit = max(0, (int) ($item['price_jpy'] ?? 0));
        $add($title, 48, $y, 9);
        $add((string) $qty, 360, $y, 9);
        $add('JPY ' . number_format($unit), 405, $y, 9);
        $add('JPY ' . number_format($unit * $qty), 485, $y, 9);
        $y -= 18;
    }

    $y = min($y - 16, 170);
    $add('Subtotal', 390, $y, 10);
    $add('JPY ' . number_format((int) ($order['subtotal_jpy'] ?? 0)), 485, $y, 10);
    $y -= 17;
    $add('Shipping', 390, $y, 10);
    $add('JPY ' . number_format((int) ($order['shipping_jpy'] ?? 0)), 485, $y, 10);
    if ((int) ($order['paypal_fee_jpy'] ?? 0) > 0) {
        $y -= 17;
        $add('PayPal fee', 390, $y, 10);
        $add('JPY ' . number_format((int) $order['paypal_fee_jpy']), 485, $y, 10);
    }
    $y -= 22;
    $add('TOTAL', 390, $y, 12, 'F2');
    $add('JPY ' . number_format((int) ($order['total_jpy'] ?? 0)), 485, $y, 12, 'F2');
    $add('This receipt records the order submitted to MegaCards.jp. The payment status above applies.', 48, 55, 8);
    $add('MegaCards.jp | Shipped from Japan', 48, 38, 8);

    $stream = implode("\n", $commands) . "\n";
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        6 => '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . 'endstream',
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $number => $object) {
        $offsets[$number] = strlen($pdf);
        $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 7\n0000000000 65535 f \n";
    for ($i = 1; $i <= 6; $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    $pdf .= "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
    return $pdf;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $orders = member_orders_for_member($sessionMember, $ordersFile, $statusFile);
    $receipt = trim((string) ($_GET['receipt'] ?? ''));
    if ($receipt !== '') {
        $selected = null;
        foreach ($orders as $order) if ((string) ($order['order_number'] ?? '') === $receipt) { $selected = $order; break; }
        if ($selected === null) member_orders_json(404, ['ok' => false, 'error' => 'Order not found.']);
        $pdf = member_orders_pdf($selected);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="MegaCards-receipt-' . preg_replace('/[^A-Za-z0-9_-]/', '', $receipt) . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }
    member_orders_json(200, ['ok' => true, 'orders' => $orders]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    member_orders_json(405, ['ok' => false, 'error' => 'Method not allowed.']);
}
if (!member_orders_origin_allowed()) member_orders_json(403, ['ok' => false, 'error' => 'Origin not allowed.']);

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input) || ($input['action'] ?? '') !== 'record' || !is_array($input['order'] ?? null)) {
    member_orders_json(400, ['ok' => false, 'error' => 'Invalid order request.']);
}
$incoming = $input['order'];
$secureToken = trim((string) ($incoming['order_token'] ?? ''));
$secureNumber = trim((string) ($incoming['order_number'] ?? ''));
if ($secureToken !== '') {
    $secureOrder = mc_get_order($secureNumber, $secureToken);
    if (!is_array($secureOrder) || !hash_equals(strtolower((string) $sessionMember['email']), strtolower((string) $secureOrder['email']))) {
        member_orders_json(403, ['ok' => false, 'error' => 'Order authorization failed.']);
    }
    $incoming = [
        'order_number' => $secureOrder['order_number'], 'name' => $secureOrder['name'], 'email' => $secureOrder['email'],
        'address' => $secureOrder['address'],
        'items' => array_map(static fn(array $item): array => [
            'id' => $item['id'], 'title' => $item['title'], 'qty' => $item['qty'], 'price_jpy' => $item['unit_price_jpy'],
        ], array_values(array_filter($secureOrder['items'] ?? [], 'is_array'))),
        'subtotal_jpy' => $secureOrder['subtotal_jpy'], 'shipping_jpy' => $secureOrder['shipping']['price_jpy'],
        'paypal_fee_jpy' => $secureOrder['processing_fee_jpy'], 'total_jpy' => $secureOrder['total_jpy'],
        'shipping_carrier' => $secureOrder['shipping']['carrier'], 'shipping_service' => $secureOrder['shipping']['service'],
        'payment_method' => $secureOrder['payment_method'],
    ];
}
$orderNumber = trim((string) ($incoming['order_number'] ?? ''));
if (!preg_match('/^[A-Za-z0-9_-]{6,40}$/', $orderNumber)) member_orders_json(422, ['ok' => false, 'error' => 'Invalid order number.']);
$items = member_orders_clean_items($incoming['items'] ?? []);
if (!$items) member_orders_json(422, ['ok' => false, 'error' => 'Order items are required.']);

$record = [
    'order_number' => $orderNumber,
    'member_id' => (string) $sessionMember['id'],
    'customer_name' => mb_substr(trim((string) ($incoming['name'] ?? $sessionMember['name'] ?? '')), 0, 80),
    'email' => strtolower(trim((string) ($incoming['email'] ?? $sessionMember['email']))),
    'address' => member_orders_clean_address($incoming['address'] ?? []),
    'items' => $items,
    'subtotal_jpy' => max(0, (int) ($incoming['subtotal_jpy'] ?? 0)),
    'shipping_jpy' => max(0, (int) ($incoming['shipping_jpy'] ?? $incoming['shipping_price'] ?? 0)),
    'paypal_fee_jpy' => max(0, (int) ($incoming['paypal_fee_jpy'] ?? $incoming['paypal_fee'] ?? 0)),
    'total_jpy' => max(0, (int) ($incoming['total_jpy'] ?? 0)),
    'shipping_carrier' => mb_substr(trim((string) ($incoming['shipping_carrier'] ?? '')), 0, 40),
    'shipping_service' => mb_substr(trim((string) ($incoming['shipping_service'] ?? '')), 0, 80),
    'payment_method' => in_array(($incoming['payment_method'] ?? ''), ['wise', 'paypal'], true) ? (string) $incoming['payment_method'] : '',
    'status' => 'Pending payment',
    'created_at' => gmdate('c'),
];

$directory = dirname($ordersFile);
if (!is_dir($directory) && !@mkdir($directory, 0755, true)) member_orders_json(500, ['ok' => false, 'error' => 'Order history is unavailable.']);
$handle = @fopen($ordersFile, 'c+');
if (!$handle || !flock($handle, LOCK_EX)) member_orders_json(500, ['ok' => false, 'error' => 'Order history is unavailable.']);
try {
    rewind($handle);
    $raw = stream_get_contents($handle);
    $stored = $raw !== false && trim($raw) !== '' ? json_decode($raw, true) : [];
    if (!is_array($stored)) $stored = [];
    $existing = $stored[$orderNumber] ?? null;
    if (is_array($existing) && (string) ($existing['member_id'] ?? '') !== (string) $sessionMember['id']) {
        member_orders_json(409, ['ok' => false, 'error' => 'Order number already exists.']);
    }
    if (is_array($existing)) $record['created_at'] = (string) ($existing['created_at'] ?? $record['created_at']);
    $stored[$orderNumber] = $record;
    $encoded = json_encode($stored, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($encoded)) member_orders_json(500, ['ok' => false, 'error' => 'Could not save the order history.']);
    rewind($handle);
    if (!ftruncate($handle, 0) || fwrite($handle, $encoded . PHP_EOL) === false || !fflush($handle)) {
        member_orders_json(500, ['ok' => false, 'error' => 'Could not save the order history.']);
    }
    @chmod($ordersFile, 0640);
} finally {
    flock($handle, LOCK_UN);
    fclose($handle);
}
member_orders_json(201, ['ok' => true, 'order_number' => $orderNumber]);
