<?php
declare(strict_types=1);

require_once __DIR__ . '/exchange_rate_lib.php';

const MC_ORDER_RESERVATION_SECONDS = 600;
const MC_ORDER_RATE_LIMIT = 20;

function mc_private_config(): array
{
    static $config = null;
    if (is_array($config)) return $config;
    $path = getenv('MEGACARDS_PRIVATE_CONFIG') ?: dirname(__DIR__, 2) . '/megacards-private.php';
    if (!is_file($path)) throw new RuntimeException('Private configuration is unavailable.');
    $loaded = require $path;
    if (!is_array($loaded)) throw new RuntimeException('Private configuration is invalid.');
    $config = $loaded;
    return $config;
}

function mc_json_response(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mc_require_same_origin(): void
{
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    $candidate = $origin !== '' ? $origin : $referer;
    $candidateHost = strtolower((string) parse_url($candidate, PHP_URL_HOST));
    if ($host === '' || $candidateHost === '' || !hash_equals($host, $candidateHost)) {
        mc_json_response(403, ['error' => 'Origin not allowed.']);
    }
}

function mc_rate_limit(string $bucket, int $limit = MC_ORDER_RATE_LIMIT, int $window = 600): void
{
    $ip = filter_var((string) ($_SERVER['REMOTE_ADDR'] ?? ''), FILTER_VALIDATE_IP) ?: 'unknown';
    $file = __DIR__ . '/data/rate_' . hash('sha256', $bucket . '|' . $ip) . '.json';
    $handle = @fopen($file, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) mc_json_response(503, ['error' => 'Please try again.']);
    try {
        rewind($handle);
        $raw = stream_get_contents($handle);
        $values = $raw !== false ? json_decode($raw, true) : [];
        if (!is_array($values)) $values = [];
        $cutoff = time() - $window;
        $values = array_values(array_filter($values, static fn($value): bool => is_int($value) && $value >= $cutoff));
        if (count($values) >= $limit) mc_json_response(429, ['error' => 'Too many requests. Please wait and try again.']);
        $values[] = time();
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($values));
        fflush($handle);
        @chmod($file, 0640);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function mc_b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function mc_b64url_decode(string $value): string|false
{
    $padding = strlen($value) % 4;
    if ($padding) $value .= str_repeat('=', 4 - $padding);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function mc_sign_payload(array $payload): string
{
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) throw new RuntimeException('Could not sign payload.');
    $secret = (string) (mc_private_config()['order_hmac_secret'] ?? '');
    if (strlen($secret) < 32) throw new RuntimeException('Signing configuration is unavailable.');
    return mc_b64url_encode($json) . '.' . mc_b64url_encode(hash_hmac('sha256', $json, $secret, true));
}

function mc_verify_payload(string $token): ?array
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) return null;
    $json = mc_b64url_decode($parts[0]);
    $signature = mc_b64url_decode($parts[1]);
    if (!is_string($json) || !is_string($signature)) return null;
    $secret = (string) (mc_private_config()['order_hmac_secret'] ?? '');
    $expected = hash_hmac('sha256', $json, $secret, true);
    if (!hash_equals($expected, $signature)) return null;
    $payload = json_decode($json, true);
    if (!is_array($payload) || (int) ($payload['exp'] ?? 0) < time()) return null;
    return $payload;
}

function mc_catalog_all(): array
{
    $file = __DIR__ . '/data/catalog_products.json';
    $decoded = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
    return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
}

function mc_shipping_profiles(): array
{
    $file = __DIR__ . '/data/shipping_products.json';
    $decoded = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
    return is_array($decoded) ? $decoded : [];
}

function mc_clean_cart(mixed $raw): array
{
    if (!is_array($raw)) return [];
    $cart = [];
    foreach ($raw as $entry) {
        if (!is_array($entry)) continue;
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($entry['id'] ?? '')) ?? '';
        $quantity = max(1, min(99, (int) ($entry['quantity'] ?? $entry['qty'] ?? 0)));
        if ($id !== '') $cart[$id] = min(99, ($cart[$id] ?? 0) + $quantity);
    }
    ksort($cart);
    return $cart;
}

function mc_cart_hash(array $cart): string
{
    ksort($cart);
    return hash('sha256', json_encode($cart, JSON_UNESCAPED_SLASHES) ?: '');
}

function mc_orders_file(): string
{
    return __DIR__ . '/data/secure_orders.json';
}

function mc_orders_read_handle($handle): array
{
    rewind($handle);
    $raw = stream_get_contents($handle);
    $orders = $raw !== false && trim($raw) !== '' ? json_decode($raw, true) : [];
    return is_array($orders) ? $orders : [];
}

function mc_expire_orders(array &$orders): bool
{
    $changed = false;
    $now = time();
    foreach ($orders as &$order) {
        if (!is_array($order)) continue;
        if (in_array((string) ($order['status'] ?? ''), ['reserved', 'pending_payment'], true)
            && (int) ($order['reservation_expires_at'] ?? 0) < $now) {
            $order['status'] = 'expired';
            $order['expired_at'] = gmdate('c');
            $changed = true;
        }
    }
    unset($order);
    return $changed;
}

function mc_orders_write_handle($handle, array $orders): void
{
    $encoded = json_encode($orders, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($encoded)) throw new RuntimeException('Could not save order data.');
    rewind($handle);
    if (!ftruncate($handle, 0) || fwrite($handle, $encoded . PHP_EOL) === false || !fflush($handle)) {
        throw new RuntimeException('Could not save order data.');
    }
    @chmod(mc_orders_file(), 0640);
}

function mc_reserved_quantities(array $orders, ?string $excludeOrder = null): array
{
    $reserved = [];
    $now = time();
    foreach ($orders as $number => $order) {
        if (!is_array($order) || ($excludeOrder !== null && (string) $number === $excludeOrder)) continue;
        if (!in_array((string) ($order['status'] ?? ''), ['reserved', 'pending_payment'], true)) continue;
        if ((int) ($order['reservation_expires_at'] ?? 0) < $now) continue;
        foreach (($order['items'] ?? []) as $item) {
            if (!is_array($item)) continue;
            $id = (string) ($item['id'] ?? '');
            if ($id !== '') $reserved[$id] = ($reserved[$id] ?? 0) + max(0, (int) ($item['qty'] ?? 0));
        }
    }
    return $reserved;
}

function mc_available_stock_map(): array
{
    $handle = @fopen(mc_orders_file(), 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) return [];
    try {
        $orders = mc_orders_read_handle($handle);
        $changed = mc_expire_orders($orders);
        if ($changed) mc_orders_write_handle($handle, $orders);
        $reserved = mc_reserved_quantities($orders);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
    $available = [];
    foreach (mc_catalog_all() as $product) {
        $id = (string) ($product['id'] ?? '');
        if ($id === '') continue;
        $available[$id] = max(0, (int) ($product['stock'] ?? 1) - (int) ($reserved[$id] ?? 0));
    }
    return $available;
}

function mc_server_cart(array $cart, array $reserved = []): array
{
    $catalog = [];
    foreach (mc_catalog_all() as $product) {
        if (($product['status'] ?? 'active') === 'active') $catalog[(string) ($product['id'] ?? '')] = $product;
    }
    $profiles = mc_shipping_profiles();
    $profileMap = is_array($profiles['products'] ?? null) ? $profiles['products'] : [];
    $packagingWeight = max(0, (int) ($profiles['packaging_weight_g'] ?? 250));
    $padding = max(0, (float) ($profiles['padding_cm'] ?? 4));
    $items = [];
    $subtotal = 0;
    $weight = $packagingWeight;
    $width = 1.0;
    $depth = 1.0;
    $height = 0.0;
    foreach ($cart as $id => $qty) {
        $product = $catalog[$id] ?? null;
        if (!is_array($product)) throw new InvalidArgumentException('A product in the cart is unavailable.');
        $stock = max(0, (int) ($product['stock'] ?? 1) - (int) ($reserved[$id] ?? 0));
        if ($qty > $stock) throw new InvalidArgumentException('The requested quantity is no longer available.');
        $unit = max(0, (int) ($product['jpy'] ?? 0)) + 990;
        if ($unit <= 990) throw new InvalidArgumentException('A product price is unavailable.');
        $profile = is_array($profileMap[$id] ?? null) ? $profileMap[$id] : $product;
        $itemWeight = max(1, (int) ($profile['weight_g'] ?? 450));
        $itemWidth = max(1, (float) ($profile['width_cm'] ?? 14));
        $itemDepth = max(1, (float) ($profile['depth_cm'] ?? 14));
        $itemHeight = max(1, (float) ($profile['height_cm'] ?? 5));
        $items[] = ['id' => $id, 'title' => (string) ($product['title'] ?? 'Japanese TCG box'), 'qty' => $qty, 'unit_price_jpy' => $unit, 'weight_g' => $itemWeight];
        $subtotal += $unit * $qty;
        $weight += $itemWeight * $qty;
        $width = max($width, $itemWidth);
        $depth = max($depth, $itemDepth);
        $height += $itemHeight * $qty;
    }
    if (!$items) throw new InvalidArgumentException('The cart is empty.');
    return [
        'items' => $items,
        'subtotal_jpy' => $subtotal,
        'parcel' => ['weight' => (int) ceil($weight), 'width' => (int) ceil($width + $padding), 'depth' => (int) ceil($depth + $padding), 'height' => (int) ceil($height + $padding)],
    ];
}

function mc_create_reserved_order(array $request, array $quote): array
{
    $cart = mc_clean_cart($request['cart'] ?? []);
    if (mc_cart_hash($cart) !== (string) ($quote['cart_hash'] ?? '')) throw new InvalidArgumentException('The shipping quote does not match the cart.');
    $address = is_array($request['address'] ?? null) ? $request['address'] : [];
    $state = strtoupper(trim((string) ($address['state'] ?? '')));
    $zip = trim((string) ($address['zip'] ?? ''));
    if ($state !== (string) ($quote['state'] ?? '') || $zip !== (string) ($quote['zip'] ?? '')) throw new InvalidArgumentException('The shipping quote does not match the address.');
    if (!preg_match('/^\d{4}$/', $zip) || !in_array($state, ['NSW','VIC','QLD','WA','SA','TAS','ACT','NT'], true)) throw new InvalidArgumentException('Enter a valid Australian address.');
    $name = trim((string) ($request['name'] ?? ''));
    $email = strtolower(trim((string) ($request['email'] ?? '')));
    $phone = trim((string) ($request['phone'] ?? ''));
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '' || trim((string) ($address['line1'] ?? '')) === '' || trim((string) ($address['city'] ?? '')) === '') {
        throw new InvalidArgumentException('Complete all delivery details.');
    }
    $payment = in_array(($request['payment_method'] ?? ''), ['wise', 'paypal'], true) ? (string) $request['payment_method'] : 'wise';
    $ordersFile = mc_orders_file();
    $handle = @fopen($ordersFile, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) throw new RuntimeException('Order service is temporarily unavailable.');
    try {
        $orders = mc_orders_read_handle($handle);
        mc_expire_orders($orders);
        $reserved = mc_reserved_quantities($orders);
        $serverCart = mc_server_cart($cart, $reserved);
        $shipping = max(0, (int) ($quote['price_jpy'] ?? 0));
        $processing = $payment === 'paypal' ? (int) round(($serverCart['subtotal_jpy'] + $shipping) * 0.05) : 0;
        $orderNumber = gmdate('ymdHis') . strtoupper(bin2hex(random_bytes(3)));
        while (isset($orders[$orderNumber])) $orderNumber = gmdate('ymdHis') . strtoupper(bin2hex(random_bytes(3)));
        $accessToken = bin2hex(random_bytes(32));
        $expires = time() + MC_ORDER_RESERVATION_SECONDS;
        $order = [
            'order_number' => $orderNumber,
            'access_token_hash' => hash('sha256', $accessToken),
            'status' => 'reserved',
            'reservation_expires_at' => $expires,
            'payment_expires_at' => $expires,
            'created_at' => gmdate('c'),
            'name' => mb_substr($name, 0, 80),
            'email' => $email,
            'phone' => mb_substr($phone, 0, 30),
            'address' => [
                'line1' => mb_substr(trim((string) ($address['line1'] ?? '')), 0, 120),
                'line2' => mb_substr(trim((string) ($address['line2'] ?? '')), 0, 120),
                'city' => mb_substr(trim((string) ($address['city'] ?? '')), 0, 80),
                'state' => $state, 'zip' => $zip, 'country' => 'AU', 'phone' => mb_substr($phone, 0, 30),
            ],
            'items' => $serverCart['items'],
            'subtotal_jpy' => $serverCart['subtotal_jpy'],
            'shipping' => ['carrier' => (string) ($quote['carrier'] ?? ''), 'service' => (string) ($quote['service'] ?? ''), 'price_jpy' => $shipping],
            'processing_fee_jpy' => $processing,
            'total_jpy' => $serverCart['subtotal_jpy'] + $shipping + $processing,
            'aud_jpy_rate' => mc_aud_jpy_rate(),
            'payment_method' => $payment,
        ];
        $orders[$orderNumber] = $order;
        mc_orders_write_handle($handle, $orders);
        $order['access_token'] = $accessToken;
        return $order;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function mc_get_order(string $number, string $token = '', bool $allowAdmin = false): ?array
{
    if (!preg_match('/^[A-Z0-9_-]{8,40}$/i', $number)) return null;
    $handle = @fopen(mc_orders_file(), 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) return null;
    try {
        $orders = mc_orders_read_handle($handle);
        $changed = mc_expire_orders($orders);
        if ($changed) mc_orders_write_handle($handle, $orders);
        $order = $orders[$number] ?? null;
        if (!is_array($order)) return null;
        if (!$allowAdmin && ($token === '' || !hash_equals((string) ($order['access_token_hash'] ?? ''), hash('sha256', $token)))) return null;
        return $order;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function mc_update_order(string $number, callable $callback): ?array
{
    $handle = @fopen(mc_orders_file(), 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) return null;
    try {
        $orders = mc_orders_read_handle($handle);
        mc_expire_orders($orders);
        if (!is_array($orders[$number] ?? null)) return null;
        $updated = $callback($orders[$number]);
        if (!is_array($updated)) return null;
        $orders[$number] = $updated;
        mc_orders_write_handle($handle, $orders);
        return $updated;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function mc_find_order_by_paypal_invoice(string $invoiceId): ?array
{
    if ($invoiceId === '') return null;
    $handle = @fopen(mc_orders_file(), 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) return null;
    try {
        $orders = mc_orders_read_handle($handle);
        foreach ($orders as $number => $order) {
            if (!is_array($order) || !hash_equals((string) ($order['paypal_invoice_id'] ?? ''), $invoiceId)) continue;
            $order['order_number'] = (string) $number;
            return $order;
        }
        return null;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function mc_record_member_order_status(string $number, string $status, string $tracking = ''): void
{
    $file = __DIR__ . '/data/mc_order_status.json';
    $handle = @fopen($file, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) return;
    try {
        rewind($handle);
        $raw = stream_get_contents($handle);
        $data = $raw !== false && trim($raw) !== '' ? json_decode($raw, true) : [];
        if (!is_array($data)) $data = [];
        $data[$number] = ['status' => $status, 'tracking' => $tracking, 'updated_at' => gmdate('c')];
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (is_string($encoded)) {
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, $encoded . PHP_EOL);
            fflush($handle);
            @chmod($file, 0640);
        }
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function mc_finalize_order_payment(string $number): ?array
{
    $ordersHandle = @fopen(mc_orders_file(), 'c+');
    if (!$ordersHandle || !flock($ordersHandle, LOCK_EX)) return null;
    $catalogFile = __DIR__ . '/data/catalog_products.json';
    $catalogHandle = null;
    try {
        $orders = mc_orders_read_handle($ordersHandle);
        $order = $orders[$number] ?? null;
        if (!is_array($order)) return null;
        if (($order['status'] ?? '') === 'paid') return $order;
        if (!in_array((string) ($order['status'] ?? ''), ['reserved', 'pending_payment', 'expired'], true)) return null;
        $catalogHandle = @fopen($catalogFile, 'c+');
        if (!$catalogHandle || !flock($catalogHandle, LOCK_EX)) return null;
        rewind($catalogHandle);
        $raw = stream_get_contents($catalogHandle);
        $catalog = $raw !== false ? json_decode($raw, true) : [];
        if (!is_array($catalog)) return null;
        foreach (($order['items'] ?? []) as $item) {
            $found = false;
            foreach ($catalog as &$product) {
                if ((string) ($product['id'] ?? '') !== (string) ($item['id'] ?? '')) continue;
                $current = max(0, (int) ($product['stock'] ?? 1));
                $qty = max(0, (int) ($item['qty'] ?? 0));
                if ($qty > $current) throw new RuntimeException('Stock is lower than the paid quantity.');
                $product['stock'] = $current - $qty;
                $product['updated_at'] = gmdate('c');
                $found = true;
                break;
            }
            unset($product);
            if (!$found) throw new RuntimeException('A paid product no longer exists.');
        }
        $encoded = json_encode(array_values($catalog), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($encoded)) throw new RuntimeException('Could not update stock.');
        rewind($catalogHandle);
        if (!ftruncate($catalogHandle, 0) || fwrite($catalogHandle, $encoded . PHP_EOL) === false || !fflush($catalogHandle)) throw new RuntimeException('Could not update stock.');
        $order['status'] = 'paid';
        $order['paid_at'] = gmdate('c');
        $orders[$number] = $order;
        mc_orders_write_handle($ordersHandle, $orders);
        mc_record_member_order_status($number, 'Paid');
        return $order;
    } finally {
        if (is_resource($catalogHandle)) { flock($catalogHandle, LOCK_UN); fclose($catalogHandle); }
        flock($ordersHandle, LOCK_UN);
        fclose($ordersHandle);
    }
}
