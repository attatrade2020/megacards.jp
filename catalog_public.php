<?php
declare(strict_types=1);

require_once __DIR__ . '/order_core.php';
require_once __DIR__ . '/exchange_rate_lib.php';

const MC_DOMESTIC_SHIPPING_JPY = 990;

function mc_catalog_products(): array
{
    $file = __DIR__ . '/data/catalog_products.json';
    if (!is_file($file)) return [];
    $products = json_decode((string) file_get_contents($file), true);
    if (!is_array($products)) return [];
    return array_values(array_filter($products, static function ($product): bool {
        return is_array($product) && ($product['status'] ?? 'active') === 'active' && !empty($product['id']);
    }));
}

function mc_catalog_product(string $id): ?array
{
    foreach (mc_catalog_products() as $product) {
        if (hash_equals((string) $product['id'], $id)) return $product;
    }
    return null;
}

function mc_product_price_jpy(array $product): int
{
    return max(0, (int) ($product['jpy'] ?? 0)) + MC_DOMESTIC_SHIPPING_JPY;
}

function mc_product_price_aud(array $product): int
{
    return (int) round(mc_product_price_jpy($product) / mc_aud_jpy_rate());
}

function mc_product_stock(array $product): int
{
    static $available = null;
    if (!is_array($available)) $available = mc_available_stock_map();
    $id = (string) ($product['id'] ?? '');
    return $id !== '' && array_key_exists($id, $available)
        ? max(0, (int) $available[$id])
        : max(0, (int) ($product['stock'] ?? 1));
}

function mc_product_image_url(array $product): string
{
    $path = ltrim(trim((string) ($product['image'] ?? '')), '/');
    if ($path === '' || !preg_match('#^(assets/products/|img/)#', $path)) return '';
    return 'https://megacards.jp/' . str_replace('%2F', '/', rawurlencode($path));
}

function mc_product_brand(array $product): string
{
    return match (strtolower((string) ($product['category'] ?? ''))) {
        'pokemon' => 'Pokémon',
        'onepiece' => 'Bandai',
        'yugioh' => 'Konami',
        default => 'Japanese TCG',
    };
}

function mc_product_category(array $product): string
{
    return match (strtolower((string) ($product['category'] ?? ''))) {
        'pokemon' => 'Pokémon Card Game',
        'onepiece' => 'ONE PIECE Card Game',
        'yugioh' => 'Yu-Gi-Oh! OCG',
        default => 'Japanese Trading Card Game',
    };
}

function mc_product_type(array $product): string
{
    return 'Trading Card Games > ' . mc_product_category($product) . ' > Sealed Boxes';
}

function mc_product_description(array $product): string
{
    $title = trim((string) ($product['title'] ?? 'Japanese trading card box'));
    return $title . ' is a sealed Japanese-edition trading card box supplied from Japan. The displayed product price is in Australian dollars. Tracked DHL or FedEx delivery to Australia is calculated separately.';
}

function mc_product_package(array $product): array
{
    $weight = max(1, (int) ($product['weight_g'] ?? 450)) + 250;
    $width = max(1, (float) ($product['width_cm'] ?? 14)) + 4;
    $depth = max(1, (float) ($product['depth_cm'] ?? 14)) + 4;
    $height = max(1, (float) ($product['height_cm'] ?? 5)) + 4;
    return ['weight_g' => $weight, 'width_cm' => $width, 'depth_cm' => $depth, 'height_cm' => $height];
}

function mc_product_shipping_aud(array $product): int
{
    $package = mc_product_package($product);
    $chargeableKg = max($package['weight_g'] / 1000, ($package['width_cm'] * $package['depth_cm'] * $package['height_cm']) / 5000);
    return min(250, max(32, (int) ceil(20 + (10 * $chargeableKg))));
}

function mc_product_url(array $product): string
{
    return 'https://megacards.jp/product/' . rawurlencode((string) $product['id']);
}
