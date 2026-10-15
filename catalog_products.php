<?php
declare(strict_types=1);

require_once __DIR__ . '/order_core.php';

session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$catalogFile = __DIR__ . '/data/catalog_products.json';
$deletedFile = __DIR__ . '/data/catalog_deleted_products.json';
$shippingFile = __DIR__ . '/data/shipping_products.json';
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

function readJsonArray(string $path): array {
    if (!is_file($path)) return [];
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function writeJson(string $path, array $data): bool {
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    $tmp = $path . '.tmp';
    if (@file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) return false;
    return @rename($tmp, $path);
}

function cleanupDeletedProducts(array $deletedProducts, array $remainingProducts, string $deletedFile, string $shippingFile): bool {
    $tombstones = readJsonArray($deletedFile);
    foreach ($deletedProducts as $product) {
        $id = (string) ($product['id'] ?? '');
        if ($id === '') continue;
        $tombstones[$id] = [
            'id' => $id,
            'source_url' => (string) ($product['source_url'] ?? ''),
            'image' => (string) ($product['image'] ?? ''),
            'deleted_at' => gmdate('c'),
        ];
    }
    if (!writeJson($deletedFile, $tombstones)) return false;

    $shipping = readJsonArray($shippingFile);
    if (isset($shipping['products']) && is_array($shipping['products'])) {
        foreach ($deletedProducts as $product) unset($shipping['products'][(string) ($product['id'] ?? '')]);
        $shipping['updated_at'] = gmdate('c');
        if (!writeJson($shippingFile, $shipping)) return false;
    }

    $remainingImages = [];
    foreach ($remainingProducts as $product) {
        $image = ltrim((string) ($product['image'] ?? ''), '/');
        if ($image !== '') $remainingImages[$image] = true;
    }
    foreach ($deletedProducts as $product) {
        $image = ltrim((string) ($product['image'] ?? ''), '/');
        if ($image === '' || isset($remainingImages[$image]) || !preg_match('#^(assets/products/|img/products/)#', $image)) continue;
        $absolute = __DIR__ . '/' . $image;
        if (is_file($absolute)) @unlink($absolute);
    }
    return true;
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
    $availableStock = mc_available_stock_map();
    foreach ($products as &$product) {
        $id = (string) ($product['id'] ?? '');
        $product['stock'] = max(0, (int) ($availableStock[$id] ?? ($product['stock'] ?? 1)));
    }
    unset($product);
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
    $deleted = readJsonArray($deletedFile);
    $sourceUrl = trim((string) ($incoming['source_url'] ?? ''));
    $deletedMatch = isset($deleted[$requestedId]);
    if (!$deletedMatch && $sourceUrl !== '') {
        foreach ($deleted as $tombstone) {
            if (is_array($tombstone) && (string) ($tombstone['source_url'] ?? '') === $sourceUrl) { $deletedMatch = true; break; }
        }
    }
    if ($deletedMatch && empty($input['restore_deleted'])) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => '削除済みの商品です。再登録する場合は商品追加から明示的に登録してください。'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($deletedMatch) {
        unset($deleted[$requestedId]);
        foreach ($deleted as $key => $tombstone) {
            if ($sourceUrl !== '' && is_array($tombstone) && (string) ($tombstone['source_url'] ?? '') === $sourceUrl) unset($deleted[$key]);
        }
        if (!writeJson($deletedFile, $deleted)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => '削除履歴を更新できませんでした。'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
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

if ($action === 'delete' || $action === 'delete_many') {
    $ids = $action === 'delete_many' ? ($input['ids'] ?? []) : [(string) ($input['id'] ?? '')];
    if (!is_array($ids)) $ids = [];
    $ids = array_values(array_unique(array_filter(array_map('strval', $ids), static fn(string $id): bool => $id !== '')));
    $idMap = array_fill_keys($ids, true);
    $deletedProducts = array_values(array_filter($products, static fn(array $p): bool => isset($idMap[(string) ($p['id'] ?? '')])));
    $products = array_values(array_filter($products, static fn(array $p): bool => !isset($idMap[(string) ($p['id'] ?? '')])));
    if (!$deletedProducts) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => '商品が見つかりません。'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!writeCatalog($catalogFile, $products)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => '商品データを保存できませんでした。'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!cleanupDeletedProducts($deletedProducts, $products, $deletedFile, $shippingFile)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => '関連データを完全に削除できませんでした。'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => true, 'deleted_ids' => array_values(array_map(static fn(array $p): string => (string) $p['id'], $deletedProducts))]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action']);
