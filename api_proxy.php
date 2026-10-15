<?php
declare(strict_types=1);
// api_proxy.php - Proxy GET requests publicly and POST requests for authenticated administrators.
require_once __DIR__ . '/order_core.php';
header('Content-Type: application/json; charset=utf-8');

if (!function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode(['error' => 'curl not available on this server']);
    exit;
}

$GAS_URL = 'https://script.google.com/macros/s/AKfycbyhZu5LMJPiG5VGmAz9ctJ2O6BHsrA7r3-Ej3WLoh6SqiQKzquWURAYG0BWV4GPEAMi/exec';

// ===== GET: proxy GAS GET requests with disk cache + gzip =====
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $action = $_GET['action'] ?? '';

    // Cache aggressively for products (5 minutes), settings (1 minute), nothing else
    $cacheTTL = 0;
    if ($action === 'products' || $action === 'products_lite') $cacheTTL = 300;     // 5 min
    elseif ($action === 'settings') $cacheTTL = 60;  // 1 min

    // Strip ignorable cache-bust params for cache key (so ?t=12345 doesn't fragment cache)
    parse_str($query, $qParts);
    unset($qParts['t'], $qParts['_'], $qParts['cb']);
    ksort($qParts);
    $cacheKey = md5(http_build_query($qParts));
    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir) && !@mkdir($dataDir, 0755, true)) $dataDir = sys_get_temp_dir();
    $cacheFile   = $dataDir . '/mc_apicache_' . $cacheKey . '.json';
    $cacheFileGz = $cacheFile . '.gz';

    // Detect client gzip support
    $accept = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    $clientGzip = (stripos($accept, 'gzip') !== false);

    // Serve from cache if valid
    if ($cacheTTL > 0 && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
        header('Cache-Control: public, max-age=' . $cacheTTL);
        header('X-Cache: HIT');
        if ($clientGzip && file_exists($cacheFileGz)) {
            header('Content-Encoding: gzip');
            header('Vary: Accept-Encoding');
            readfile($cacheFileGz);
        } else {
            readfile($cacheFile);
        }
        exit;
    }

    // For products_lite, fetch the full products and filter+slim server-side.
    $upstreamQuery = $query;
    if ($action === 'products_lite') {
        // Re-fetch with action=products from GAS
        $upQ = $qParts;
        $upQ['action'] = 'products';
        unset($upQ['lite'], $upQ['_lite']);
        $upstreamQuery = http_build_query($upQ);
    }
    $url = $GAS_URL . ($upstreamQuery ? '?' . $upstreamQuery : '');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING => 'gzip', // ask GAS for gzipped response too
    ]);
    $result = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($result === false) {
        http_response_code(502);
        header('Cache-Control: no-store');
        echo json_encode(['error' => 'GAS GET failed: ' . $err]);
        exit;
    }

    // Validate response is JSON (GAS sometimes returns HTML error pages on quota/concurrency limits)
    // — never cache error responses (would poison the cache).
    $resultDecoded = json_decode($result, true);
    $isValidJson = is_array($resultDecoded);
    if (!$isValidJson) {
        // Likely a GAS error HTML page — try to serve stale cache instead of poisoning fresh cache
        if (file_exists($cacheFile)) {
            header('Cache-Control: public, max-age=60');
            header('X-Cache: STALE-after-error');
            if ($clientGzip && file_exists($cacheFileGz)) {
                header('Content-Encoding: gzip');
                header('Vary: Accept-Encoding');
                readfile($cacheFileGz);
            } else {
                readfile($cacheFile);
            }
            exit;
        }
        // No fallback cache — return error JSON
        http_response_code(503);
        header('Cache-Control: no-store');
        $snippet = mb_substr(strip_tags($result), 0, 200);
        echo json_encode(['error' => 'GAS returned non-JSON (possibly rate-limited)', 'snippet' => $snippet]);
        exit;
    }

    // Slim transform for products_lite: drop hidden + strip rarely-used fields
    if ($action === 'products_lite') {
        if (!empty($resultDecoded['products']) && is_array($resultDecoded['products'])) {
            $keep = ['id','title_en','title_jp','category','icon','price_jpy','price_usd',
                     'weight_g','shrinkwrap','status','stock','sort','release_date',
                     'image_url','condition','snkrdunk_url','notes'];
            $out = [];
            foreach ($resultDecoded['products'] as $p) {
                if (!is_array($p)) continue;
                if (($p['status'] ?? 'active') === 'hidden') continue;
                $row = [];
                foreach ($keep as $k) if (array_key_exists($k, $p)) $row[$k] = $p[$k];
                $out[] = $row;
            }
            $resultDecoded['products'] = $out;
            $result = json_encode($resultDecoded, JSON_UNESCAPED_UNICODE);
        }
    }

    // Persist to cache (only valid JSON reaches here)
    if ($cacheTTL > 0) {
        @file_put_contents($cacheFile, $result);
        @file_put_contents($cacheFileGz, gzencode($result, 6));
    }

    if ($cacheTTL > 0) {
        header('Cache-Control: public, max-age=' . $cacheTTL);
    } else {
        header('Cache-Control: no-cache, no-store, must-revalidate');
    }
    header('X-Cache: MISS');

    if ($clientGzip) {
        header('Content-Encoding: gzip');
        header('Vary: Accept-Encoding');
        echo gzencode($result, 6);
    } else {
        echo $result;
    }
    exit;
}

// ===== POST: proxy GAS POST requests (rates, orders) =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
mc_require_same_origin();
if (empty($_SESSION['mc_admin_authenticated'])) mc_json_response(401, ['error' => 'Administrator login required.']);

$input = file_get_contents('php://input');

// Step 1: POST to GAS, don't follow redirects (to capture Location header)
$ch = curl_init($GAS_URL);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $input,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($input)
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to connect to GAS: ' . $curlError]);
    exit;
}

$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

// If direct JSON response (no redirect)
if ($httpCode === 200) {
    $decoded = json_decode($body, true);
    if ($decoded !== null) {
        echo $body;
        exit;
    }
}

// Extract Location header for redirect
$redirectUrl = null;
if (preg_match('/^Location:\s*(.+)$/mi', $headers, $m)) {
    $redirectUrl = trim($m[1]);
}

if (!$redirectUrl) {
    http_response_code(502);
    echo json_encode([
        'error' => 'No redirect from GAS',
        'http_code' => $httpCode,
        'body_preview' => substr($body, 0, 200)
    ]);
    exit;
}

// Step 2: GET the redirect URL to fetch the JSON response
$ch2 = curl_init($redirectUrl);
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$result = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$curlError2 = curl_error($ch2);
curl_close($ch2);

if ($result === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to fetch redirect: ' . $curlError2]);
    exit;
}

// Validate JSON
$decoded = json_decode($result, true);
if ($decoded !== null) {
    echo $result;
    exit;
}

// Not JSON
http_response_code(502);
echo json_encode([
    'error' => 'GAS returned non-JSON',
    'http_code' => $httpCode2,
    'body_preview' => substr(strip_tags($result), 0, 200)
]);
