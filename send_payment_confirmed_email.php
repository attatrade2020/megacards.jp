<?php
declare(strict_types=1);
require_once __DIR__ . '/order_core.php';
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') mc_json_response(405, ['error' => 'POST required.']);
mc_require_same_origin();
if (empty($_SESSION['mc_admin_authenticated'])) mc_json_response(401, ['error' => 'Administrator login required.']);
$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) mc_json_response(400, ['error' => 'Invalid request.']);
$secureNumber = trim((string) ($input['order_number'] ?? ''));
$secureOrder = mc_get_order($secureNumber, '', true);
if (is_array($secureOrder)) {
    try {
        $secureOrder = mc_finalize_order_payment($secureNumber);
    } catch (Throwable $error) {
        error_log('MegaCards stock finalization error: ' . $error->getMessage());
        mc_json_response(409, ['error' => 'Payment was recorded, but stock could not be updated. Check the order inventory.']);
    }
    if (!is_array($secureOrder)) mc_json_response(409, ['error' => 'The secure order could not be finalized.']);
    $input = [
        'email' => $secureOrder['email'], 'name' => $secureOrder['name'], 'order_number' => $secureOrder['order_number'],
        'payment_method' => $secureOrder['payment_method'], 'total_jpy' => $secureOrder['total_jpy'],
        'display_currency_code' => 'AUD', 'display_currency_symbol' => 'A$', 'aud_jpy_rate' => $secureOrder['aud_jpy_rate'] ?? 110.04,
        'items' => array_map(static fn(array $item): array => [
            'id' => (string) ($item['id'] ?? ''), 'title' => (string) ($item['title'] ?? ''),
            'qty' => (int) ($item['qty'] ?? 1), 'price_jpy' => (int) ($item['unit_price_jpy'] ?? 0),
        ], array_values(array_filter($secureOrder['items'] ?? [], 'is_array'))),
    ];
}

$to = filter_var(trim((string)($input['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '';
$name = $input['name'] ?? '';
$orderNumber = $input['order_number'] ?? '';
$paymentMethod = $input['payment_method'] ?? '';
$totalJpy = intval($input['total_jpy'] ?? 0);
$items = $input['items'] ?? [];

if (!$to || !$orderNumber) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing email or order_number']);
    exit;
}

// The invoice is paid in JPY. Recover the order-time AUD estimate rate for display.
$curCode = strtoupper(trim((string)($input['display_currency_code'] ?? '')));
$curSymbol = (string)($input['display_currency_symbol'] ?? '');
$audJpyRate = floatval($input['aud_jpy_rate'] ?? 0);
if ($curCode === '' || $audJpyRate <= 0) {
    $cf = __DIR__ . '/data/mc_order_items_cache.json';
    if (file_exists($cf)) {
        $allCache = json_decode(@file_get_contents($cf), true);
        if (is_array($allCache) && isset($allCache[$orderNumber])) {
            $entry = $allCache[$orderNumber];
            if (!empty($entry['display_currency_code'])) $curCode = strtoupper($entry['display_currency_code']);
            elseif (!empty($entry['currency_code'])) $curCode = strtoupper($entry['currency_code']);
            if (!empty($entry['display_currency_symbol'])) $curSymbol = $entry['display_currency_symbol'];
            elseif (!empty($entry['currency_symbol'])) $curSymbol = $entry['currency_symbol'];
            if (!empty($entry['aud_jpy_rate'])) $audJpyRate = floatval($entry['aud_jpy_rate']);
            elseif (!empty($entry['currency_rate'])) {
                $legacyRate = floatval($entry['currency_rate']);
                $audJpyRate = $legacyRate > 0 && $legacyRate < 1 ? 1 / $legacyRate : $legacyRate;
            }
            // Also enrich items with id+price if missing in input
            if (empty($items) && !empty($entry['items'])) $items = $entry['items'];
        }
    }
}
if ($curCode === '') $curCode = 'AUD';
if ($curSymbol === '') $curSymbol = 'A$';
if ($audJpyRate <= 0) $audJpyRate = 110.04;
function _mc_dual_price($jpy, $curCode, $curSymbol, $audJpyRate) {
    $jpy = intval($jpy);
    if ($curCode === 'AUD' && $audJpyRate > 0) {
        $conv = round($jpy / $audJpyRate);
        return htmlspecialchars($curSymbol, ENT_QUOTES, 'UTF-8') . number_format($conv);
    }
    return 'A$' . number_format(round($jpy / $audJpyRate));
}

// Build items table rows (with dual currency)
$itemRows = '';
foreach ($items as $item) {
    $title = htmlspecialchars($item['title'] ?? '');
    $qty = intval($item['qty'] ?? 1);
    $lineTotalJpy = intval($item['price_jpy'] ?? 0) * $qty;
    $lineTotal = _mc_dual_price($lineTotalJpy, $curCode, $curSymbol, $audJpyRate);
    $itemRows .= "<tr>
        <td style='padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:14px;'>{$title}</td>
        <td style='padding:10px 12px;border-bottom:1px solid #f0f0f0;text-align:center;font-size:14px;'>{$qty}</td>
        <td class='mc-price' style='padding:10px 12px;border-bottom:1px solid #f0f0f0;text-align:right;font-size:14px;white-space:nowrap;'>{$lineTotal}</td>
    </tr>";
}

$totalDual = _mc_dual_price($totalJpy, $curCode, $curSymbol, $audJpyRate);
$methodLabel = $paymentMethod === 'paypal' ? 'PayPal' : 'Wise';
$nameEsc = htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8');
$orderNumberSafe = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$orderNumber);
$orderNumberEsc = htmlspecialchars($orderNumberSafe, ENT_QUOTES, 'UTF-8');

$subject = "MegaCards.jp - Payment Confirmed & Shipping Soon #{$orderNumberSafe}";

$html = "<!DOCTYPE html>
<html>
<head>
<meta charset='utf-8'>
<meta name='viewport' content='width=device-width,initial-scale=1'>
<style>
@media only screen and (max-width:640px) {
  .mc-shell { width:100% !important; }
  .mc-pad { padding:24px 16px !important; }
  .mc-price { white-space:normal !important; }
}
</style>
</head>
<body style='margin:0;padding:0;background:#f5f5f0;font-family:Arial,Helvetica,sans-serif;'>
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
        <div style='background:#e8f5e9;border:1px solid #a5d6a7;border-radius:10px;padding:22px;margin:0 0 24px 0;text-align:center;'>
            <div style='font-size:42px;line-height:1;margin-bottom:8px;'>✅</div>
            <p style='font-size:18px;color:#2e7d32;font-weight:700;margin:0 0 6px 0;'>Payment Confirmed</p>
            <p style='font-size:13px;color:#555;margin:0;'>We have received your {$methodLabel} payment. Thank you!</p>
        </div>

        <h2 style='margin:0 0 12px;font-size:18px;color:#1a1a2e;'>Hi {$nameEsc},</h2>
        <p style='font-size:14px;color:#555;margin:0 0 16px;line-height:1.7;'>
            Great news! Your payment for order <strong>#{$orderNumberEsc}</strong> has been confirmed.
            We are now preparing your items for shipment.
        </p>

        <!-- Shipping info box -->
        <div style='background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:18px;margin:20px 0;'>
            <p style='font-size:14px;color:#8d6e00;font-weight:700;margin:0 0 8px 0;'>📦 Shipping Information</p>
            <ul style='margin:0;padding-left:20px;font-size:13px;color:#555;line-height:1.7;'>
                <li>Your order will be packed and shipped within <strong>3–10 business days</strong> (handling time).</li>
                <li>Once shipped, you will receive another email with the <strong>tracking number</strong>.</li>
                <li>All items are shipped directly from Japan. Only the carrier's <strong>standard insurance</strong> is included.</li>
                <li>Import duties, GST, VAT, or customs fees (if any) are the buyer's responsibility.</li>
            </ul>
        </div>

        <!-- Order Info -->
        <table width='100%' style='margin:20px 0 12px;'>
            <tr>
                <td style='padding:6px 0;font-size:13px;color:#888;'>Order Number:</td>
                <td style='padding:6px 0;font-size:14px;font-weight:700;'>{$orderNumberEsc}</td>
            </tr>
            <tr>
                <td style='padding:6px 0;font-size:13px;color:#888;'>Payment Method:</td>
                <td style='padding:6px 0;font-size:14px;'>{$methodLabel}</td>
            </tr>
            <tr>
                <td style='padding:6px 0;font-size:13px;color:#888;'>Status:</td>
                <td style='padding:6px 0;font-size:14px;color:#2e7d32;font-weight:700;'>Payment Confirmed — Preparing for Shipment</td>
            </tr>
        </table>

        <!-- Items -->
        <table width='100%' cellpadding='0' cellspacing='0' style='border:1px solid #eee;border-radius:8px;overflow:hidden;margin-bottom:16px;'>
            <tr style='background:#f8f8f8;'>
                <th style='padding:10px 12px;text-align:left;font-size:13px;color:#888;font-weight:600;'>Item</th>
                <th style='padding:10px 12px;text-align:center;font-size:13px;color:#888;font-weight:600;'>Qty</th>
                <th style='padding:10px 12px;text-align:right;font-size:13px;color:#888;font-weight:600;'>Price</th>
            </tr>
            {$itemRows}
            <tr style='background:#f8f8f8;'>
                <td colspan='2' style='padding:12px;text-align:right;font-size:15px;font-weight:700;'>Total Paid:</td>
                <td class='mc-price' style='padding:12px;text-align:right;font-size:15px;font-weight:700;color:#1a1a2e;white-space:nowrap;'>{$totalDual}</td>
            </tr>
        </table>
        <p style='font-size:12px;color:#6b7280;margin:8px 0 0;line-height:1.6;text-align:right;'>All prices above are displayed in Australian dollars (A$).</p>

        <p style='font-size:13px;color:#888;margin:20px 0 0;line-height:1.7;'>
            If you have any questions, please reply to this email or contact us at
            <a href='mailto:info@atta-jp.com' style='color:#1a1a2e;'>info@atta-jp.com</a>.
        </p>
    </td></tr>

    <!-- Footer -->
    <tr><td style='background:#f8f8f8;padding:24px 32px;border-top:1px solid #eee;'>
        <p style='font-size:12px;color:#bbb;margin:0;text-align:center;'>
            © " . date('Y') . " MegaCards.jp — Atta Trade | Licensed Dealer #451460009881
        </p>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>";

$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: MegaCards.jp <noreply@megacards.jp>\r\n";
$headers .= "Reply-To: info@atta-jp.com\r\n";

$sent = mail($to, $subject, $html, $headers);

// Also notify admin
$adminTo = 'info@atta-trade.com';
if (strtolower(trim($to)) !== strtolower(trim($adminTo))) {
    $adminSubject = "[MegaCards] Payment Confirmed #{$orderNumberSafe} - " . preg_replace('/[\r\n]+/', ' ', (string)$name);
    mail($adminTo, $adminSubject, $html, $headers);
}

if ($sent) {
    echo json_encode(['ok' => true, 'message' => 'Payment confirmation email sent']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send email']);
}
