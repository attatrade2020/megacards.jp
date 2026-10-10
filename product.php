<?php
declare(strict_types=1);

require_once __DIR__ . '/catalog_public.php';

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '' && preg_match('#/product/([^/?]+)#', (string) ($_SERVER['REQUEST_URI'] ?? ''), $match)) {
    $id = rawurldecode($match[1]);
}
$product = $id !== '' ? mc_catalog_product($id) : null;
if ($product === null) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, follow');
    echo '<!doctype html><html lang="en-AU"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Product not found | MegaCards.jp</title></head><body><main><h1>Product not found</h1><p><a href="/">Return to MegaCards.jp</a></p></main></body></html>';
    exit;
}

function mc_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$title = trim((string) ($product['title'] ?? 'Japanese trading card box'));
$series = trim((string) ($product['series'] ?? mc_product_category($product)));
$release = trim((string) ($product['release'] ?? ''));
$image = mc_product_image_url($product);
$price = mc_product_price_aud($product);
$stock = mc_product_stock($product);
$shipping = mc_product_shipping_aud($product);
$canonical = mc_product_url($product);
$description = mc_product_description($product);
$category = mc_product_category($product);
$brand = mc_product_brand($product);
$schema = [
    '@context' => 'https://schema.org/',
    '@graph' => [
        [
            '@type' => 'Product',
            '@id' => $canonical . '#product',
            'name' => $title,
            'description' => $description,
            'image' => [$image],
            'sku' => (string) $product['id'],
            'brand' => ['@type' => 'Brand', 'name' => $brand],
            'category' => $category,
            'offers' => [
                '@type' => 'Offer',
                'url' => $canonical,
                'priceCurrency' => 'AUD',
                'price' => number_format($price, 2, '.', ''),
                'priceValidUntil' => date('Y-m-d', strtotime('+1 year')),
                'availability' => $stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => ['@type' => 'Organization', 'name' => 'MegaCards.jp', 'url' => 'https://megacards.jp/'],
                'shippingDetails' => [
                    '@type' => 'OfferShippingDetails',
                    'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => number_format($shipping, 2, '.', ''), 'currency' => 'AUD'],
                    'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'AU'],
                    'shippingOrigin' => ['@type' => 'DefinedRegion', 'addressCountry' => 'JP'],
                    'deliveryTime' => [
                        '@type' => 'ShippingDeliveryTime',
                        'handlingTime' => ['@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 3, 'unitCode' => 'DAY'],
                        'transitTime' => ['@type' => 'QuantitativeValue', 'minValue' => 4, 'maxValue' => 7, 'unitCode' => 'DAY'],
                    ],
                ],
                'hasMerchantReturnPolicy' => [
                    '@type' => 'MerchantReturnPolicy',
                    'applicableCountry' => 'AU',
                    'returnPolicyCategory' => 'https://schema.org/MerchantReturnNotPermitted',
                    'merchantReturnLink' => 'https://megacards.jp/terms.html#returns',
                ],
            ],
        ],
        [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://megacards.jp/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $category, 'item' => 'https://megacards.jp/#shop'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $title, 'item' => $canonical],
            ],
        ],
    ],
];
$schemaJson = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=900');
?>
<!doctype html>
<html lang="en-AU">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= mc_h($title) ?> | MegaCards.jp</title>
  <meta name="description" content="<?= mc_h($description) ?>">
  <link rel="canonical" href="<?= mc_h($canonical) ?>">
  <link rel="alternate" hreflang="en-AU" href="<?= mc_h($canonical) ?>">
  <link rel="alternate" hreflang="x-default" href="<?= mc_h($canonical) ?>">
  <meta property="og:type" content="product">
  <meta property="og:title" content="<?= mc_h($title) ?>">
  <meta property="og:description" content="<?= mc_h($description) ?>">
  <meta property="og:url" content="<?= mc_h($canonical) ?>">
  <meta property="og:image" content="<?= mc_h($image) ?>">
  <meta property="product:price:amount" content="<?= $price ?>.00">
  <meta property="product:price:currency" content="AUD">
  <meta property="product:availability" content="<?= $stock > 0 ? 'in stock' : 'out of stock' ?>">
  <script type="application/ld+json"><?= $schemaJson ?></script>
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-SPWWT45MZF"></script>
  <script src="/ga4_attribution.js"></script>
  <style>
    *{box-sizing:border-box}body{margin:0;color:#0b1428;background:#f4f6fa;font-family:Arial,sans-serif;line-height:1.55}a{color:inherit}.header{background:#0b1428}.header__inner{max-width:1120px;margin:auto;padding:18px 24px;display:flex;align-items:center;justify-content:space-between}.brand{color:#fff;text-decoration:none;font-size:1.25rem;font-weight:800}.brand span{color:#ffd447}.back{color:#d8e1f2;font-size:.84rem}.main{max-width:1120px;margin:auto;padding:46px 24px 70px}.crumb{margin-bottom:20px;color:#647087;font-size:.8rem}.product{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:48px;padding:38px;background:#fff;border:1px solid #e1e6ef;border-radius:20px;box-shadow:0 16px 50px rgba(11,20,40,.08)}.image{display:grid;place-items:center;min-height:430px;background:#f7f8fb;border-radius:14px;overflow:hidden}.image img{width:100%;height:100%;max-height:520px;object-fit:contain}.kicker{margin:0 0 8px;color:#1459e9;font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}h1{margin:0;font-size:clamp(1.7rem,4vw,2.6rem);line-height:1.15;letter-spacing:-.03em}.series{color:#647087}.stock{display:inline-block;padding:5px 10px;color:#16784a;background:#e8f7ef;border-radius:999px;font-size:.75rem;font-weight:800}.stock--out{color:#9d2b2b;background:#fff0f0}.price{margin:24px 0 4px;font-size:2.2rem;font-weight:900}.price small{font-size:.85rem}.shipping{margin:0 0 24px;color:#647087;font-size:.82rem}.description{color:#3f4a5f}.purchase{display:grid;grid-template-columns:100px 1fr;gap:12px;margin-top:26px}.purchase input{width:100%;min-height:52px;padding:10px;border:1px solid #cfd7e5;border-radius:10px;text-align:center;font:inherit}.purchase button{min-height:52px;padding:12px 20px;color:#0b1428;background:#ffd447;border:0;border-radius:10px;cursor:pointer;font-weight:900}.purchase button:disabled{cursor:not-allowed;opacity:.55}.fine{margin-top:16px;color:#707b8f;font-size:.74rem}.links{margin-top:28px;display:flex;gap:18px;color:#1459e9;font-size:.8rem;font-weight:700}@media(max-width:760px){.product{grid-template-columns:1fr;padding:22px;gap:26px}.image{min-height:290px}.purchase{grid-template-columns:82px 1fr}}
  </style>
</head>
<body>
  <header class="header"><div class="header__inner"><a class="brand" href="/">MegaCards<span>.jp</span></a><a class="back" href="/#shop">All boxes</a></div></header>
  <main class="main">
    <nav class="crumb"><a href="/">Home</a> / <a href="/#shop"><?= mc_h($category) ?></a> / <?= mc_h($title) ?></nav>
    <article class="product">
      <div class="image"><img src="<?= mc_h($image) ?>" alt="<?= mc_h($title) ?>" width="640" height="640" fetchpriority="high"></div>
      <div>
        <p class="kicker"><?= mc_h($category) ?></p>
        <h1><?= mc_h($title) ?></h1>
        <p class="series"><?= mc_h($series) ?><?= $release !== '' ? ' · ' . mc_h($release) : '' ?></p>
        <span class="stock<?= $stock > 0 ? '' : ' stock--out' ?>"><?= $stock > 0 ? 'In stock' : 'Sold out' ?></span>
        <p class="price"><small>AUD</small> A$<?= number_format($price) ?></p>
        <p class="shipping">Estimated tracked shipping for one box: A$<?= number_format($shipping) ?>. The exact DHL or FedEx rate is calculated for your postcode in the cart.</p>
        <p class="description"><?= mc_h($description) ?></p>
        <form class="purchase" id="purchaseForm">
          <input id="quantity" type="number" min="1" max="<?= max(1, min(99, $stock)) ?>" value="1" aria-label="Quantity" <?= $stock > 0 ? '' : 'disabled' ?>>
          <button type="submit" <?= $stock > 0 ? '' : 'disabled' ?>><?= $stock > 0 ? 'Add to cart' : 'Currently sold out' ?></button>
        </form>
        <p class="fine">Product prices are in Australian dollars. International shipping, Australian GST, customs charges and payment-provider conversion are separate where applicable. Returns and cancellations are not accepted after payment.</p>
        <div class="links"><a href="/faq.php">FAQ</a><a href="/terms.html">Terms</a></div>
      </div>
    </article>
  </main>
<?php if ($stock > 0): ?>
  <script>document.getElementById('purchaseForm').addEventListener('submit',function(event){event.preventDefault();const quantity=Math.max(1,Math.min(<?= max(1, min(99, $stock)) ?>,Number(document.getElementById('quantity').value)||1));window.location.href='/?add=<?= rawurlencode((string) $product['id']) ?>&qty='+quantity+'#shop';});</script>
<?php endif; ?>
</body>
</html>
