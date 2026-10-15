<?php
declare(strict_types=1);
require_once __DIR__ . '/order_core.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') mc_json_response(405, ['error' => 'POST required.']);
mc_require_same_origin();
mc_rate_limit('order-email', 8, 600);
$request = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($request)) mc_json_response(400, ['error' => 'Invalid request.']);
$requestedNumber = trim((string) ($request['order_number'] ?? ''));
$requestedToken = trim((string) ($request['order_token'] ?? ''));
$secureOrder = mc_get_order($requestedNumber, $requestedToken);
if (!is_array($secureOrder)) mc_json_response(403, ['error' => 'Order authorization failed.']);
$gatedPayUrl = 'https://megacards.jp/pay.php?order=' . rawurlencode($requestedNumber) . '&token=' . rawurlencode($requestedToken);
$secureItems = array_map(static fn(array $item): array => [
    'id' => (string) ($item['id'] ?? ''), 'title' => (string) ($item['title'] ?? ''),
    'qty' => (int) ($item['qty'] ?? 1), 'price_jpy' => (int) ($item['unit_price_jpy'] ?? 0),
], array_values(array_filter($secureOrder['items'] ?? [], 'is_array')));
$input = [
    'email' => $secureOrder['email'], 'name' => $secureOrder['name'], 'order_number' => $secureOrder['order_number'],
    'payment_method' => $secureOrder['payment_method'], 'country' => 'AU', 'contact' => $secureOrder['phone'],
    'address' => $secureOrder['address'], 'total_jpy' => $secureOrder['total_jpy'], 'subtotal_jpy' => $secureOrder['subtotal_jpy'],
    'shipping_price' => $secureOrder['shipping']['price_jpy'], 'shipping_carrier' => $secureOrder['shipping']['carrier'],
    'shipping_service' => $secureOrder['shipping']['service'], 'items' => $secureItems,
    'invoice_currency' => 'JPY', 'display_currency_code' => 'AUD', 'display_currency_symbol' => 'A$',
    'aud_jpy_rate' => $secureOrder['aud_jpy_rate'] ?? 110.04, 'paypal_fee' => $secureOrder['processing_fee_jpy'] ?? 0,
    'expiry_str' => date('j M Y, g:i a T', (int) $secureOrder['payment_expires_at']),
    (($secureOrder['payment_method'] ?? '') === 'paypal' ? 'paypal_pay_url' : 'wise_pay_url') => $gatedPayUrl,
];

$to = filter_var(trim((string)($input['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '';
$name = $input['name'] ?? '';
$orderNumber = $input['order_number'] ?? '';
$paymentMethod = $input['payment_method'] ?? 'wise';
$totalJpy = $input['total_jpy'] ?? 0;
$subtotal = $input['subtotal_jpy'] ?? 0;
$shipping = $input['shipping_price'] ?? 0;
$shippingCarrier = $input['shipping_carrier'] ?? '';
$shippingService = $input['shipping_service'] ?? '';
$items = $input['items'] ?? [];
$paypalFee = $input['paypal_fee'] ?? 0;
$paypalTxId = $input['paypal_transaction_id'] ?? '';
$paypalPayUrl = $input['paypal_pay_url'] ?? '';
$wisePayUrl = $input['wise_pay_url'] ?? '';
$expiryStr = $input['expiry_str'] ?? '';

if (!$to || !$orderNumber) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing email or order_number']);
    exit;
}

// ===== Cache full items[] (with id + price_jpy) so admin can recover them later =====
// GAS sometimes drops fields; this acts as a reliable secondary store.
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);
$cacheFile = $dataDir . '/mc_order_items_cache.json';
$cache = [];
if (file_exists($cacheFile)) {
    $j = json_decode(@file_get_contents($cacheFile), true);
    if (is_array($j)) $cache = $j;
}
$cache[$orderNumber] = [
    'items' => $items,
    'name'  => $name,
    'email' => $to,
    'address' => $input['address'] ?? null,
    'country' => $input['country'] ?? '',
    'contact' => $input['contact'] ?? '',
    'shipping_carrier' => $shippingCarrier,
    'shipping_service' => $shippingService,
    'shipping_price'   => $shipping,
    'subtotal_jpy' => $subtotal,
    'total_jpy' => $totalJpy,
    'payment_method' => $paymentMethod,
    'invoice_currency'       => $input['invoice_currency'] ?? 'JPY',
    'display_currency_code'  => $input['display_currency_code'] ?? 'AUD',
    'display_currency_symbol'=> $input['display_currency_symbol'] ?? 'A$',
    'aud_jpy_rate'           => $input['aud_jpy_rate'] ?? 0,
    'cached_at' => date('c'),
];
// Cap at 1000 most-recent entries
if (count($cache) > 1000) {
    uasort($cache, function($a, $b) { return strcmp($b['cached_at'] ?? '', $a['cached_at'] ?? ''); });
    $cache = array_slice($cache, 0, 1000, true);
}
@file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE));

// Resolve product info (image_url, condition badge) for any item with id
function _mc_get_products() {
    static $prods = null;
    if ($prods !== null) return $prods;
    $cacheF = __DIR__ . '/data/mc_apicache_' . md5('action=products') . '.json';
    if (file_exists($cacheF) && (time() - filemtime($cacheF)) < 1800) {
        $j = json_decode(@file_get_contents($cacheF), true);
        if (is_array($j)) {
            $prods = $j['products'] ?? $j ?? [];
            return $prods;
        }
    }
    $prods = [];
    $ch = curl_init('https://megacards.jp/api_proxy.php?action=products');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    $j = json_decode($body, true);
    if (is_array($j)) $prods = $j['products'] ?? $j ?? [];
    return $prods;
}
function _mc_find_product($it, $allProducts) {
    $pid = $it['id'] ?? $it['item_id'] ?? '';
    if ($pid) {
        foreach ($allProducts as $p) if (($p['id'] ?? '') === $pid) return $p;
    }
    $t = $it['title'] ?? '';
    if ($t) {
        foreach ($allProducts as $p) {
            if (($p['title_en'] ?? '') === $t || ($p['title_jp'] ?? '') === $t) return $p;
        }
    }
    return null;
}
function _mc_normalize_cond($t) {
    $t = preg_replace('/\s+/', '', $t);
    $t = preg_replace('/^psa/i', 'PSA', $t);
    // Japanese suffix → ASCII symbols
    $t = preg_replace('/(\d+)\s*以下/u', '≤$1', $t);
    $t = preg_replace('/(\d+)\s*以上/u', '≥$1', $t);
    $t = preg_replace('/(\d+)\s*未満/u', '<$1', $t);
    $t = preg_replace('/以下|以上|未満/u', '', $t);
    return $t;
}
function _mc_condition($p) {
    if (!$p) return '';
    if (!empty($p['condition'])) return _mc_normalize_cond(trim($p['condition']));
    $notes = $p['notes'] ?? '';
    if (preg_match('/[\[\(](PSA\s*\d+[^\]\)]{0,8}|Sealed|[A-D][+\-]?)[\]\)]/iu', $notes, $m)) {
        return _mc_normalize_cond(trim($m[1]));
    }
    if (!empty($p['shrinkwrap'])) return 'SEALED';
    return '';
}
$allProducts = _mc_get_products();

// The invoice is paid in JPY. AUD is an estimate for customer convenience.
$curCode   = strtoupper(trim((string)($input['display_currency_code'] ?? ($input['currency_code'] ?? 'AUD'))));
$curSymbol = (string)($input['display_currency_symbol'] ?? ($input['currency_symbol'] ?? 'A$'));
$audJpyRate = floatval($input['aud_jpy_rate'] ?? 0);
if ($audJpyRate <= 0) {
    // Compatibility with orders created before aud_jpy_rate was introduced.
    $legacyRate = floatval($input['currency_rate'] ?? 0);
    $audJpyRate = $legacyRate > 0 && $legacyRate < 1 ? 1 / $legacyRate : $legacyRate;
}
if ($audJpyRate <= 0) $audJpyRate = 110.04;
function _mc_dual_price($jpy, $curCode, $curSymbol, $audJpyRate) {
    $jpy = intval($jpy);
    if ($curCode === 'AUD' && $audJpyRate > 0) {
        $conv = round($jpy / $audJpyRate);
        return htmlspecialchars($curSymbol, ENT_QUOTES, 'UTF-8') . number_format($conv);
    }
    return 'A$' . number_format(round($jpy / $audJpyRate));
}

// ISO-3166 alpha-2 → English name (subset; falls through for unknown / already-full names)
function _mc_country_full($code) {
    static $map = [
        'US'=>'United States','GB'=>'United Kingdom','UK'=>'United Kingdom','CA'=>'Canada','AU'=>'Australia',
        'NZ'=>'New Zealand','JP'=>'Japan','CN'=>'China','HK'=>'Hong Kong','TW'=>'Taiwan','KR'=>'South Korea',
        'SG'=>'Singapore','MY'=>'Malaysia','TH'=>'Thailand','ID'=>'Indonesia','PH'=>'Philippines','VN'=>'Vietnam',
        'IN'=>'India','DE'=>'Germany','FR'=>'France','IT'=>'Italy','ES'=>'Spain','PT'=>'Portugal','NL'=>'Netherlands',
        'BE'=>'Belgium','LU'=>'Luxembourg','CH'=>'Switzerland','AT'=>'Austria','SE'=>'Sweden','NO'=>'Norway',
        'DK'=>'Denmark','FI'=>'Finland','IE'=>'Ireland','PL'=>'Poland','CZ'=>'Czechia','SK'=>'Slovakia',
        'HU'=>'Hungary','RO'=>'Romania','BG'=>'Bulgaria','GR'=>'Greece','HR'=>'Croatia','SI'=>'Slovenia',
        'EE'=>'Estonia','LV'=>'Latvia','LT'=>'Lithuania','IS'=>'Iceland','MT'=>'Malta','CY'=>'Cyprus',
        'RU'=>'Russia','UA'=>'Ukraine','TR'=>'Turkey','IL'=>'Israel','AE'=>'United Arab Emirates','SA'=>'Saudi Arabia',
        'QA'=>'Qatar','KW'=>'Kuwait','BH'=>'Bahrain','OM'=>'Oman','EG'=>'Egypt','ZA'=>'South Africa',
        'MX'=>'Mexico','BR'=>'Brazil','AR'=>'Argentina','CL'=>'Chile','CO'=>'Colombia','PE'=>'Peru'
    ];
    $c = trim((string)$code);
    if ($c === '') return '';
    if (strlen($c) > 3) return $c;
    return $map[strtoupper($c)] ?? strtoupper($c);
}

// Build shipping address block (eBay format)
$addr = $input['address'] ?? null;
$shippingAddrBlock = '';
if (is_array($addr) && (!empty($addr['line1']) || !empty($addr['city']) || !empty($addr['zip']))) {
    $lines = [];
    if (!empty($name)) $lines[] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    if (!empty($addr['line1'])) $lines[] = htmlspecialchars($addr['line1']);
    if (!empty($addr['line2'])) $lines[] = htmlspecialchars($addr['line2']);
    $cityLine = trim(implode(', ', array_filter([$addr['city'] ?? '', $addr['state'] ?? '', $addr['zip'] ?? ''], fn($s) => $s !== '' && $s !== null)));
    if ($cityLine !== '') $lines[] = htmlspecialchars($cityLine);
    $cn = _mc_country_full($addr['country'] ?? ($input['country'] ?? ''));
    if ($cn) $lines[] = htmlspecialchars($cn);
    $ph = $addr['phone'] ?? ($input['contact'] ?? '');
    if ($ph) $lines[] = 'Tel: ' . htmlspecialchars($ph);
    $shippingAddrBlock = "
        <div style='background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:16px 18px;margin:0 0 20px 0;'>
            <p style='margin:0 0 8px 0;font-size:12px;color:#0369a1;font-weight:600;letter-spacing:0.5px;text-transform:uppercase;'>📦 Shipping Address</p>
            <p style='margin:0;font-size:14px;color:#0c4a6e;line-height:1.6;'>" . implode('<br>', $lines) . "</p>
        </div>";
}

// Build items table rows (with thumbnail + condition badge)
$itemRows = '';
foreach ($items as $item) {
    $title = htmlspecialchars($item['title'] ?? '');
    $qty = intval($item['qty'] ?? 1);
    $itemJpy = intval($item['price_jpy'] ?? 0);
    $lineTotalJpy = $itemJpy * $qty;
    $lineTotal = _mc_dual_price($lineTotalJpy, $curCode, $curSymbol, $audJpyRate);
    $prod = _mc_find_product($item, $allProducts);
    $cond = _mc_condition($prod);
    $condBadge = '';
    if ($cond) {
        $colors = ['PSA10' => ['#fef3c7', '#b45309'], 'PSA9' => ['#dbeafe', '#1d4ed8'], 'SEALED' => ['#dcfce7', '#15803d']];
        $col = $colors[$cond] ?? (preg_match('/^[A-D]/', $cond) ? ['#f3e8ff', '#7c3aed'] : ['#f3f4f6', '#6b7280']);
        $condBadge = " <span style='display:inline-block;margin-left:6px;padding:2px 8px;background:{$col[0]};color:{$col[1]};border-radius:4px;font-size:11px;font-weight:700;letter-spacing:0.3px;vertical-align:middle;'>{$cond}</span>";
    }
    $imgHtml = '';
    if ($prod && !empty($prod['image_url'])) {
        $raw = $prod['image_url'];
        if (strpos($raw, 'snkrdunk.com') !== false) {
            $imgUrl = 'https://megacards.jp/img_proxy.php?url=' . urlencode($raw) . '&size=small';
        } elseif (preg_match('#^https?://#i', $raw)) {
            $imgUrl = $raw;
        } else {
            // Relative path (e.g. "img/products/ninja-spinner.webp") — make absolute for email clients
            $imgUrl = 'https://megacards.jp/' . ltrim($raw, '/');
        }
        $imgUrlEsc = htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8');
        $imgHtml = "<img src='{$imgUrlEsc}' alt='' width='56' height='56' style='width:56px;height:56px;object-fit:contain;background:#f3f4f6;border-radius:6px;display:block;'>";
    } else {
        $imgHtml = "<div style='width:56px;height:56px;background:#f3f4f6;border-radius:6px;'></div>";
    }
    $itemRows .= "<tr>
        <td class='mc-product-image' style='padding:10px 12px;border-bottom:1px solid #f0f0f0;width:64px;'>{$imgHtml}</td>
        <td style='padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:14px;line-height:1.45;word-break:break-word;'>{$title}{$condBadge}</td>
        <td style='padding:10px 12px;border-bottom:1px solid #f0f0f0;text-align:center;font-size:14px;'>{$qty}</td>
        <td class='mc-price' style='padding:10px 12px;border-bottom:1px solid #f0f0f0;text-align:right;font-size:14px;white-space:nowrap;'>{$lineTotal}</td>
    </tr>";
}

$totalDual    = _mc_dual_price($totalJpy, $curCode, $curSymbol, $audJpyRate);
$subtotalDual = _mc_dual_price($subtotal, $curCode, $curSymbol, $audJpyRate);
$shippingDual = _mc_dual_price($shipping, $curCode, $curSymbol, $audJpyRate);
$feeDual      = _mc_dual_price($paypalFee, $curCode, $curSymbol, $audJpyRate);
$nameEsc = htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8');
$orderNumberSafe = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$orderNumber);
$orderNumberEsc = htmlspecialchars($orderNumberSafe, ENT_QUOTES, 'UTF-8');
$shippingCarrierEsc = htmlspecialchars((string)$shippingCarrier, ENT_QUOTES, 'UTF-8');
$shippingServiceEsc = htmlspecialchars((string)$shippingService, ENT_QUOTES, 'UTF-8');
$expiryEsc = htmlspecialchars((string)$expiryStr, ENT_QUOTES, 'UTF-8');
$paypalPayUrlEsc = htmlspecialchars((string)$paypalPayUrl, ENT_QUOTES, 'UTF-8');
$wisePayUrlEsc = htmlspecialchars((string)$wisePayUrl, ENT_QUOTES, 'UTF-8');

// Payment section
if ($paymentMethod === 'paypal') {
    $paymentSection = "
    <div style='background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:20px;margin:20px 0;text-align:center;'>
        <p style='font-size:14px;color:#8d6e00;margin:0 0 12px 0;'>⏱ <strong>This quote is valid for 10 minutes.</strong></p>
        <p style='font-size:13px;color:#8d6e00;margin:0 0 4px 0;'>Expires: <strong>{$expiryEsc}</strong></p>
        <p style='font-size:12px;color:#999;margin:0;'>Prices are based on live exchange rates and may change after expiry.</p>
    </div>
    <div style='text-align:center;margin:24px 0;'>
        <a href='{$paypalPayUrlEsc}' target='_blank' style='display:inline-block;padding:16px 28px;background:#0070ba;color:#fff;font-weight:700;border-radius:10px;text-decoration:none;font-size:16px;'>Pay with PayPal &rarr;</a>
    </div>
    <p style='font-size:13px;color:#888;text-align:center;'>Click the button above to complete your payment via PayPal (Goods &amp; Services).</p>";
} else {
    $paymentSection = "
    <div style='background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:20px;margin:20px 0;text-align:center;'>
        <p style='font-size:14px;color:#8d6e00;margin:0 0 12px 0;'>⏱ <strong>This quote is valid for 10 minutes.</strong></p>
        <p style='font-size:13px;color:#8d6e00;margin:0 0 4px 0;'>Expires: <strong>{$expiryEsc}</strong></p>
        <p style='font-size:12px;color:#999;margin:0;'>Prices are based on live exchange rates and may change after expiry.</p>
    </div>
    <div style='text-align:center;margin:24px 0;'>
        <a href='{$wisePayUrlEsc}' target='_blank' style='display:inline-block;padding:16px 28px;background:#9fe870;color:#000;font-weight:700;border-radius:10px;text-decoration:none;font-size:16px;'>Pay with Wise &rarr;</a>
    </div>
    <p style='font-size:13px;color:#888;text-align:center;'>Click the button above to complete your payment via Wise.</p>";
}

// Order processing fee row
$feeRow = '';
if ($paymentMethod === 'paypal' && $paypalFee > 0) {
    $feeRow = "<tr>
        <td colspan='3' style='padding:8px 12px;text-align:right;font-size:13px;color:#6b7280;'>Order processing fee</td>
        <td style='padding:8px 12px;text-align:right;font-size:13px;color:#374151;white-space:nowrap;'>{$feeDual}</td>
    </tr>";
}

$subject = "MegaCards.jp - Order Confirmation #{$orderNumberSafe}";

$html = "<!DOCTYPE html>
<html>
<head>
<meta charset='utf-8'>
<meta name='viewport' content='width=device-width,initial-scale=1'>
<style>
@media only screen and (max-width:640px) {
  .mc-shell { width:100% !important; }
  .mc-pad { padding:24px 16px !important; }
  .mc-product-image { display:none !important; width:0 !important; padding:0 !important; }
  .mc-price { white-space:normal !important; }
}
</style>
</head>
<body style='margin:0;padding:0;background:#f5f5f0;font-family:Arial,Helvetica,sans-serif;'>
<div style='display:none;max-height:0;overflow:hidden;opacity:0;'>Order {$orderNumberEsc}: payment details and order summary.</div>
<table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0' style='width:100%;background:#f5f5f0;'>
<tr><td align='center' style='padding:24px 8px;'>
<table role='presentation' class='mc-shell' width='640' cellpadding='0' cellspacing='0' border='0' style='width:100%;max-width:640px;background:#fff;'>

    <!-- Header -->
    <tr><td style='background:#0f0f23;padding:28px 32px;text-align:center;'>
        <h1 style='margin:0;font-size:24px;color:#ffd700;letter-spacing:1px;'>MegaCards<span style='color:#fff;'>.jp</span></h1>
        <p style='margin:6px 0 0;font-size:13px;color:rgba(255,255,255,0.6);'>Japanese TCG Boxes Direct from Japan</p>
    </td></tr>

    <!-- Body -->
    <tr><td class='mc-pad' style='padding:32px;'>
        <h2 style='margin:0 0 8px;font-size:20px;color:#1a1a2e;'>Order Confirmation</h2>
        <p style='font-size:14px;color:#555;margin:0 0 4px;'>Hi <strong>{$nameEsc}</strong>,</p>
        <p style='font-size:14px;color:#555;margin:0 0 20px;'>Thank you for your order! Here are your order details:</p>

        <!-- Order Info -->
        <table width='100%' style='margin-bottom:20px;'>
            <tr>
                <td style='padding:8px 0;font-size:13px;color:#888;'>Order Number:</td>
                <td style='padding:8px 0;font-size:14px;font-weight:700;'>{$orderNumberEsc}</td>
            </tr>
            <tr>
                <td style='padding:8px 0;font-size:13px;color:#888;'>Payment Method:</td>
                <td style='padding:8px 0;font-size:14px;font-weight:600;text-transform:capitalize;'>" . ($paymentMethod === 'paypal' ? 'PayPal' : 'Wise') . "</td>
            </tr>
            <tr>
                <td style='padding:8px 0;font-size:13px;color:#888;'>Shipping:</td>
                <td style='padding:8px 0;font-size:14px;'>{$shippingCarrierEsc} {$shippingServiceEsc}</td>
            </tr>
        </table>

        {$shippingAddrBlock}

        <!-- Items -->
        <table width='100%' cellpadding='0' cellspacing='0' style='border:1px solid #eee;border-radius:8px;overflow:hidden;margin-bottom:16px;'>
            <tr style='background:#f8f8f8;'>
                <th class='mc-product-image' style='padding:10px 12px;text-align:left;font-size:13px;color:#888;font-weight:600;width:64px;'></th>
                <th style='padding:10px 12px;text-align:left;font-size:13px;color:#888;font-weight:600;'>Item</th>
                <th style='padding:10px 12px;text-align:center;font-size:13px;color:#888;font-weight:600;'>Qty</th>
                <th style='padding:10px 12px;text-align:right;font-size:13px;color:#888;font-weight:600;'>Price</th>
            </tr>
            {$itemRows}
            <tr>
                <td colspan='3' style='padding:8px 12px;text-align:right;font-size:13px;color:#6b7280;'>Subtotal</td>
                <td class='mc-price' style='padding:8px 12px;text-align:right;font-size:13px;color:#374151;white-space:nowrap;'>{$subtotalDual}</td>
            </tr>
            <tr>
                <td colspan='3' style='padding:8px 12px;text-align:right;font-size:13px;color:#6b7280;'>Shipping</td>
                <td class='mc-price' style='padding:8px 12px;text-align:right;font-size:13px;color:#374151;white-space:nowrap;'>{$shippingDual}</td>
            </tr>
            {$feeRow}
            <tr style='background:#f8f8f8;'>
                <td colspan='3' style='padding:14px 12px;text-align:right;font-size:15px;font-weight:700;'>Total due</td>
                <td class='mc-price' style='padding:14px 12px;text-align:right;font-size:15px;font-weight:700;color:#1a1a2e;white-space:nowrap;'>{$totalDual}</td>
            </tr>
        </table>

        <p style='font-size:12px;color:#6b7280;margin:8px 0 0;line-height:1.6;text-align:right;'>All prices above are displayed in Australian dollars (A$). Your payment provider may apply its own exchange rate.</p>

        {$paymentSection}
    </td></tr>

    <!-- Footer -->
    <tr><td style='background:#f8f8f8;padding:24px 32px;border-top:1px solid #eee;'>
        <p style='font-size:12px;color:#999;margin:0 0 8px;line-height:1.6;'>
            <strong>Important:</strong> Import duties, GST, VAT, or customs fees may apply. These charges are the buyer's responsibility.
        </p>
        <p style='font-size:12px;color:#bbb;margin:0;text-align:center;'>
            © " . date('Y') . " MegaCards.jp — Atta Trade | Licensed Dealer #451460009881
        </p>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>";

// Send to customer
$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: MegaCards.jp <noreply@megacards.jp>\r\n";
$headers .= "Reply-To: info@atta-jp.com\r\n";

$sent = mail($to, $subject, $html, $headers);

// Also send notification to admin (skip if same as customer)
$adminTo = 'info@atta-trade.com';
if (strtolower(trim($to)) !== strtolower(trim($adminTo))) {
    $adminSubject = "[MegaCards] New Order #{$orderNumberSafe} - " . preg_replace('/[\r\n]+/', ' ', (string)$name) . " ({$paymentMethod})";
    mail($adminTo, $adminSubject, $html, $headers);
}

if ($sent) {
    echo json_encode(['ok' => true, 'message' => 'Email sent']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send email']);
}
