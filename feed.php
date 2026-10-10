<?php
declare(strict_types=1);

require_once __DIR__ . '/catalog_public.php';

header('Content-Type: application/rss+xml; charset=utf-8');
header('Cache-Control: public, max-age=900');

function mc_feed_xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

$country = strtoupper(trim((string) ($_GET['country'] ?? 'AU')));
if ($country !== 'AU') $country = 'AU';

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n<channel>\n";
$xml .= "  <title>MegaCards.jp Australia product feed</title>\n";
$xml .= "  <link>https://megacards.jp/</link>\n";
$xml .= "  <description>Sealed Japanese Pokémon, ONE PIECE and Yu-Gi-Oh! boxes shipped from Japan.</description>\n";

foreach (mc_catalog_products() as $product) {
    $id = (string) ($product['id'] ?? '');
    $title = trim((string) ($product['title'] ?? ''));
    $image = mc_product_image_url($product);
    $price = mc_product_price_aud($product);
    if ($id === '' || $title === '' || $image === '' || $price <= 0) continue;
    $stock = mc_product_stock($product);
    $package = mc_product_package($product);
    $shipping = mc_product_shipping_aud($product);

    $xml .= "  <item>\n";
    $xml .= '    <g:id>' . mc_feed_xml($id) . "</g:id>\n";
    $xml .= '    <g:title>' . mc_feed_xml(mb_substr($title, 0, 150)) . "</g:title>\n";
    $xml .= '    <g:description>' . mc_feed_xml(mc_product_description($product)) . "</g:description>\n";
    $xml .= '    <g:link>' . mc_feed_xml(mc_product_url($product)) . "</g:link>\n";
    $xml .= '    <g:image_link>' . mc_feed_xml($image) . "</g:image_link>\n";
    $xml .= '    <g:availability>' . ($stock > 0 ? 'in_stock' : 'out_of_stock') . "</g:availability>\n";
    $xml .= '    <g:price>' . number_format($price, 2, '.', '') . " AUD</g:price>\n";
    $xml .= '    <g:brand>' . mc_feed_xml(mc_product_brand($product)) . "</g:brand>\n";
    $xml .= "    <g:condition>new</g:condition>\n";
    $xml .= "    <g:google_product_category>1247</g:google_product_category>\n";
    $xml .= '    <g:product_type>' . mc_feed_xml(mc_product_type($product)) . "</g:product_type>\n";
    $xml .= "    <g:identifier_exists>false</g:identifier_exists>\n";
    $xml .= '    <g:shipping_weight>' . (int) $package['weight_g'] . " g</g:shipping_weight>\n";
    $xml .= '    <g:shipping_length>' . number_format((float) $package['depth_cm'], 1, '.', '') . " cm</g:shipping_length>\n";
    $xml .= '    <g:shipping_width>' . number_format((float) $package['width_cm'], 1, '.', '') . " cm</g:shipping_width>\n";
    $xml .= '    <g:shipping_height>' . number_format((float) $package['height_cm'], 1, '.', '') . " cm</g:shipping_height>\n";
    $xml .= "    <g:shipping>\n";
    $xml .= "      <g:country>AU</g:country>\n";
    $xml .= "      <g:service>DHL or FedEx tracked</g:service>\n";
    $xml .= '      <g:price>' . number_format($shipping, 2, '.', '') . " AUD</g:price>\n";
    $xml .= "      <g:min_handling_time>1</g:min_handling_time>\n";
    $xml .= "      <g:max_handling_time>3</g:max_handling_time>\n";
    $xml .= "      <g:min_transit_time>4</g:min_transit_time>\n";
    $xml .= "      <g:max_transit_time>7</g:max_transit_time>\n";
    $xml .= "    </g:shipping>\n";
    $xml .= "  </item>\n";
}

$xml .= "</channel>\n</rss>\n";
echo $xml;
