<?php
declare(strict_types=1);

session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$catalogFile = __DIR__ . '/data/catalog_products.json';
$categories = ['pokemon', 'onepiece', 'yugioh'];

function readCatalog(string $path): array {
    if (!is_file($path)) return [];
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
}

function writeCatalog(string $path, array $products): bool {
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;
    $json = json_encode(array_values($products), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    $tmp = $path . '.tmp';
    if (@file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) return false;
    return @rename($tmp, $path);
}

function slugify(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'box-' . date('Ymd-His');
}

function releaseOrder(string $date, int $fallback = 0): int {
    if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return (int) str_replace('-', '', $date);
    }
    return $fallback;
}

function normalizeProduct(array $input, array $existing = []): array {
    global $categories;
    $title = trim((string) ($input['title'] ?? $input['title_en'] ?? $existing['title'] ?? ''));
    $id = slugify((string) ($input['id'] ?? $existing['id'] ?? $title));
    $category = (string) ($input['category'] ?? $existing['category'] ?? 'pokemon');
    if (!in_array($category, $categories, true)) $category = 'pokemon';
    $status = (string) ($input['status'] ?? $existing['status'] ?? 'active');
    if (!in_array($status, ['active', 'hidden'], true)) $status = 'active';
    $releaseDate = trim((string) ($input['release_date'] ?? $existing['release_date'] ?? ''));
    $releaseLabel = trim((string) ($input['release'] ?? $existing['release'] ?? ''));
    if ($releaseLabel === '' && $releaseDate !== '') {
        $time = strtotime($releaseDate);
        if ($time !== false) $releaseLabel = date('j M Y', $time);
    }
    $image = trim((string) ($input['image'] ?? $input['image_url'] ?? $existing['image'] ?? ''));
    if ($image !== '' && !preg_match('#^(assets/products/|/assets/products/|img/|/img/)#', $image)) {
        throw new InvalidArgumentException('商品画像はMegaCards内のローカル画像を指定してください。');
    }
    return [
        'id' => $id,
        'category' => $category,
        'title' => $title,
        'series' => trim((string) ($input['series'] ?? $existing['series'] ?? 'Japanese TCG')),
        'release' => $releaseLabel,
        'release_date' => $releaseDate,
        'releaseOrder' => releaseOrder($releaseDate, (int) ($input['releaseOrder'] ?? $existing['releaseOrder'] ?? 0)),
        'jpy' => max(1, (int) ($input['jpy'] ?? $input['price_jpy'] ?? $existing['jpy'] ?? 0)),
        'tag' => trim((string) ($input['tag'] ?? $existing['tag'] ?? 'Japanese box')),
        'image' => ltrim($image, '/'),
        'status' => $status,
        'stock' => max(0, (int) ($input['stock'] ?? $existing['stock'] ?? 1)),
        'weight_g' => max(1, (int) ($input['weight_g'] ?? $existing['weight_g'] ?? 450)),
        'width_cm' => max(1, (float) ($input['width_cm'] ?? $existing['width_cm'] ?? 14)),
        'depth_cm' => max(1, (float) ($input['depth_cm'] ?? $existing['depth_cm'] ?? 14)),
        'height_cm' => max(1, (float) ($input['height_cm'] ?? $existing['height_cm'] ?? 5)),
        'sort' => max(0, (int) ($input['sort'] ?? $existing['sort'] ?? 0)),
        'notes' => trim((string) ($input['notes'] ?? $existing['notes'] ?? '')),
        'source_url' => trim((string) ($input['source_url'] ?? $existing['source_url'] ?? '')),
        'source_search_url' => trim((string) ($input['source_search_url'] ?? $existing['source_search_url'] ?? '')),
        'source_price_jpy' => max(0, (int) ($input['source_price_jpy'] ?? $existing['source_price_jpy'] ?? 0)),
        'created_at' => (string) ($existing['created_at'] ?? $input['created_at'] ?? gmdate('c')),
        'updated_at' => gmdate('c'),
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $products = array_values(array_filter(readCatalog($catalogFile), static fn(array $p): bool => ($p['status'] ?? 'active') === 'active'));
    $publicFields = ['id','category','title','series','release','release_date','releaseOrder','jpy','tag','image','status','stock','weight_g','width_cm','depth_cm','height_cm','sort'];
    $products = array_map(static function (array $product) use ($publicFields): array {
        $public = [];
        foreach ($publicFields as $field) if (array_key_exists($field, $product)) $public[$field] = $product[$field];
        return $public;
    }, $products);
    echo json_encode(['ok' => true, 'products' => $products], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}
if (empty($_SESSION['mc_admin_authenticated'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => '管理画面へログインしてください。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = (string) ($input['action'] ?? 'list');
$products = readCatalog($catalogFile);
if ($action === 'list') {
    echo json_encode(['ok' => true, 'products' => $products], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'save') {
    $incoming = $input['product'] ?? null;
    if (!is_array($incoming)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => '商品データがありません。']);
        exit;
    }
    $requestedId = slugify((string) ($incoming['id'] ?? $incoming['title'] ?? ''));
    $index = null;
    foreach ($products as $i => $product) {
        if (($product['id'] ?? '') === $requestedId) { $index = $i; break; }
    }
    try {
        $normalized = normalizeProduct($incoming, $index === null ? [] : $products[$index]);
    } catch (InvalidArgumentException $error) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($normalized['title'] === '' || $normalized['image'] === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => '商品名とローカル商品画像は必須です。'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($index === null) $products[] = $normalized; else $products[$index] = $normalized;
    if (!writeCatalog($catalogFile, $products)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => '商品データを保存できませんでした。'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => true, 'product' => $normalized], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'delete') {
    $id = (string) ($input['id'] ?? '');
    $before = count($products);
    $products = array_values(array_filter($products, static fn(array $p): bool => ($p['id'] ?? '') !== $id));
    if (count($products) === $before) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => '商品が見つかりません。'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!writeCatalog($catalogFile, $products)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => '商品データを保存できませんでした。'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action']);
