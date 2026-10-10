<?php
declare(strict_types=1);

require_once __DIR__ . '/catalog_public.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=900');

function mc_sitemap_xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

$today = date('Y-m-d');
$urls = [
    ['loc' => 'https://megacards.jp/', 'lastmod' => $today, 'changefreq' => 'daily', 'priority' => '1.0'],
    ['loc' => 'https://megacards.jp/faq.php', 'lastmod' => $today, 'changefreq' => 'monthly', 'priority' => '0.6'],
    ['loc' => 'https://megacards.jp/terms.html', 'lastmod' => $today, 'changefreq' => 'yearly', 'priority' => '0.3'],
    ['loc' => 'https://megacards.jp/privacy.html', 'lastmod' => $today, 'changefreq' => 'yearly', 'priority' => '0.3'],
];
foreach (mc_catalog_products() as $product) {
    $lastmod = $today;
    foreach (['updated_at', 'created_at', 'release_date'] as $field) {
        $time = strtotime((string) ($product[$field] ?? ''));
        if ($time !== false) { $lastmod = date('Y-m-d', $time); break; }
    }
    $urls[] = ['loc' => mc_product_url($product), 'lastmod' => $lastmod, 'changefreq' => 'daily', 'priority' => '0.8'];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $url) {
    echo "  <url>\n";
    echo '    <loc>' . mc_sitemap_xml($url['loc']) . "</loc>\n";
    echo '    <lastmod>' . $url['lastmod'] . "</lastmod>\n";
    echo '    <changefreq>' . $url['changefreq'] . "</changefreq>\n";
    echo '    <priority>' . $url['priority'] . "</priority>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";
