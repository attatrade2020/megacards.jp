<?php
// snkrdunk_import.php - Scrape Snkrdunk search results
// Usage:
//   GET /snkrdunk_import.php?action=fetch&url=<snkrdunk search URL>
//     -> returns JSON: { ok, count, products: [{snkr_id, listing_id, title, price_jpy, image_url, snkrdunk_url, condition}] }
//   GET /snkrdunk_import.php?action=images&apparel_id=X&listing_id=Y
//     -> returns JSON: { ok, image_urls: [...] }   (multi-image via Snkrdunk's internal v1 API)

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

$action = $_GET['action'] ?? 'fetch';

if ($action === 'images') {
    $apparelId = $_GET['apparel_id'] ?? '';
    $listingId = $_GET['listing_id'] ?? '';
    if (!$apparelId || !$listingId) {
        echo json_encode(['error' => 'apparel_id and listing_id required']);
        exit;
    }
    // Sanitize: digits only
    if (!ctype_digit($apparelId) || !ctype_digit($listingId)) {
        echo json_encode(['error' => 'invalid ids']);
        exit;
    }
    $apiUrl = "https://snkrdunk.com/v1/apparels/{$apparelId}/used/{$listingId}";
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0.0.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err || $code !== 200) {
        echo json_encode(['error' => 'API failed', 'http' => $code, 'curl' => $err]);
        exit;
    }
    $data = json_decode($body, true);
    $item = $data['apparelUsedItem'] ?? [];
    $imgs = $item['imageUrls'] ?? [];
    // Add ?size=l for higher res
    $imgs = array_map(function($u){
        if (!$u) return $u;
        $u = preg_replace('/\?size=[a-z]+/', '?size=l', $u);
        if (strpos($u, '?size=') === false) $u .= '?size=l';
        return $u;
    }, $imgs);
    echo json_encode([
        'ok' => true,
        'image_urls' => $imgs,
        'count' => count($imgs),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action !== 'fetch') {
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

$url = $_GET['url'] ?? '';
if (!$url) {
    echo json_encode(['error' => 'URL required']);
    exit;
}

// Validate it's a snkrdunk URL
if (!preg_match('#^https?://(www\.)?snkrdunk\.com/#i', $url)) {
    echo json_encode(['error' => 'snkrdunk.com URLのみ対応']);
    exit;
}

// Fetch the HTML
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: ja,en;q=0.9',
    ],
    CURLOPT_ENCODING => '',
    CURLOPT_SSL_VERIFYPEER => true,
]);
$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err || $httpCode !== 200 || !$html) {
    echo json_encode([
        'error' => 'Snkrdunkからの取得に失敗',
        'http' => $httpCode,
        'curl_err' => $err,
    ]);
    exit;
}

// Parse products from HTML
// Each product is an <a href="...apparels/X/used/Y..." aria-label="Title - ¥Price">
//   containing <img alt="..." src="https://cdn.snkrdunk.com/..." > and a <span>condition</span>

$products = [];

// A product detail URL contains better structured information than its SEO/OG tags.
// Read the visible English heading, main product carousel image, one-item price and
// release metadata. Never use og:image here because it contains the price chart.
if (preg_match('~/apparels/(\d+)(?:[/?#]|$)~i', $url, $urlMatch)
    && !preg_match('#/used/\d+#i', $url)) {
    $apparelId = $urlMatch[1];
    $title = '';
    if (preg_match('#<h1[^>]*>(.*?)</h1>#su', $html, $titleMatch)) {
        $title = trim(html_entity_decode(strip_tags($titleMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $localizedTitle = '';
    if (preg_match('#<p[^>]*class="[^"]*subName[^"]*"[^>]*>(.*?)</p>#su', $html, $localizedMatch)) {
        $localizedTitle = trim(html_entity_decode(strip_tags($localizedMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $imageUrl = '';
    if (preg_match_all('#<img\b[^>]*>#si', $html, $imageTags)) {
        foreach ($imageTags[0] as $imageTag) {
            if (strpos($imageTag, 'mainImage') === false && strpos($imageTag, 'upload_bg_removed/') === false) continue;
            if (preg_match('#\bsrc="(https://cdn\.snkrdunk\.com/(?:upload_bg_removed|products)/[^"]+)"#i', $imageTag, $imageMatch)) {
                $imageUrl = html_entity_decode($imageMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $imageUrl = preg_replace('/\?size=[a-z]+/i', '?size=l', $imageUrl);
                if (strpos($imageUrl, '?size=') === false) $imageUrl .= '?size=l';
                break;
            }
        }
    }

    $priceJpy = 0;
    if (preg_match_all('#priceAmount[^>]*>(.*?)</span>#su', $html, $priceMatches)) {
        foreach ($priceMatches[1] as $priceMarkup) {
            $priceText = preg_replace('#<!--.*?-->#s', '', $priceMarkup);
            $candidatePrice = (int) preg_replace('/\D+/', '', html_entity_decode(strip_tags($priceText), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($candidatePrice > 0) {
                $priceJpy = $candidatePrice;
                break;
            }
        }
    }

    $releaseDate = '';
    $releasePosition = strpos($html, 'releasedAt');
    if ($releasePosition !== false) {
        $releaseChunk = substr($html, $releasePosition, 120);
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $releaseChunk, $releaseMatch)) {
            $releaseDate = $releaseMatch[1];
        }
    }

    $productNumber = '';
    $numberPosition = strpos($html, 'productNumber');
    if ($numberPosition !== false) {
        $numberChunk = substr($html, $numberPosition + strlen('productNumber'), 160);
        if (preg_match('/^[^A-Za-z0-9]*([A-Za-z0-9][A-Za-z0-9_-]{2,})/', $numberChunk, $numberMatch)) {
            $productNumber = trim($numberMatch[1]);
        }
    }

    // Use the displayed count for the one-item option as MegaCards stock.
    // Snkrdunk caps this label at "99+"; MegaCards stores that as 99.
    $stock = null;
    $listingCountText = '';
    $stockPosition = strpos($html, 'listingCountText');
    if ($stockPosition !== false) {
        $stockChunk = substr($html, $stockPosition, 100);
        if (preg_match('/listingCountText[^0-9]{0,30}(\d+)(\+?)/', $stockChunk, $stockMatch)) {
            $listingCountText = $stockMatch[1] . $stockMatch[2];
            $stock = min(99, max(0, (int) $stockMatch[1]));
        } elseif (stripos($stockChunk, 'Sold out') !== false) {
            $listingCountText = 'Sold out';
            $stock = 0;
        }
    }

    if ($title !== '' && $priceJpy > 0 && $imageUrl !== '') {
        $products[] = [
            'snkr_id'        => 'snkr_' . $apparelId,
            'apparel_id'     => $apparelId,
            'listing_id'     => '',
            'title'          => $title,
            'title_en'       => $title,
            'title_ja'       => $localizedTitle,
            'price_jpy'      => $priceJpy,
            'image_url'      => $imageUrl,
            'snkrdunk_url'   => $url,
            'condition'      => 'BOX',
            'release_date'   => $releaseDate,
            'product_number' => $productNumber,
            'stock'          => $stock,
            'stock_text'     => $listingCountText,
        ];
    }
}

$pattern = '#<a\s+href="([^"]*apparels/(\d+)/used/(\d+)[^"]*)"\s+class="[^"]*"\s+aria-label="([^"]+)"\s*>(.*?)</a>#s';

if (!$products && preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $m) {
        $href      = $m[1];
        $apparelId = $m[2];
        $listingId = $m[3];
        $ariaLabel = $m[4];
        $inner     = $m[5];

        // Decode HTML entities from aria-label
        $ariaLabel = html_entity_decode($ariaLabel, ENT_QUOTES, 'UTF-8');

        // Extract title and price from aria-label: "Title - ¥1,234"
        $title = $ariaLabel;
        $priceJpy = 0;
        if (preg_match('/^(.*?)\s*-\s*¥([\d,]+)\s*$/u', $ariaLabel, $am)) {
            $title    = trim($am[1]);
            $priceJpy = (int) str_replace(',', '', $am[2]);
        }

        // Extract main image (first img with cdn.snkrdunk.com src that's a listing image)
        $imageUrl = '';
        if (preg_match('#<img[^>]+alt="[^"]*"[^>]+src="(https://cdn\.snkrdunk\.com/apparel_used_listings/[^"]+)"#', $inner, $im)) {
            $imageUrl = html_entity_decode($im[1], ENT_QUOTES, 'UTF-8');
            // Use larger size if available
            $imageUrl = preg_replace('/\?size=[a-z]+/', '?size=l', $imageUrl);
            if (strpos($imageUrl, '?size=') === false) {
                $imageUrl .= '?size=l';
            }
        }

        // Extract condition label (PSA10, PSA9, etc)
        $condition = '';
        if (preg_match('#__conditionLabel[^"]*"[^>]*><span>([^<]+)</span>#', $inner, $cm)) {
            $condition = trim($cm[1]);
        }

        // Make sure the URL is absolute
        if (strpos($href, 'http') !== 0) {
            $href = 'https://snkrdunk.com' . $href;
        }

        $products[] = [
            'snkr_id'       => 'snkr_' . $listingId,
            'apparel_id'    => $apparelId,
            'listing_id'    => $listingId,
            'title'         => $title,
            'price_jpy'     => $priceJpy,
            'image_url'     => $imageUrl,
            'snkrdunk_url' => $href,
            'condition'     => $condition,
        ];
    }
}

echo json_encode([
    'ok'       => true,
    'count'    => count($products),
    'products' => $products,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
