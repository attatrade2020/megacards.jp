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

$to = $input['email'] ?? '';
$name = $input['name'] ?? '';
$orderNumber = $input['order_number'] ?? '';
$tracking = trim($input['tracking_number'] ?? '');
$carrier = $input['shipping_carrier'] ?? '';
$service = $input['shipping_service'] ?? '';
$items = $input['items'] ?? [];

if (!$to || !$orderNumber || !$tracking) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing email, order_number, or tracking_number']);
    exit;
}

// Build tracking URL based on carrier (with service-string fallback + number-format detection)
function trackingUrl($carrier, $service, $tracking) {
    // Combine carrier + service for matching (carrier alone may be empty)
    $blob = strtolower(trim($carrier . ' ' . $service));
    $t = urlencode($tracking);
    $tRaw = trim($tracking);

    // Carrier name match
    if (strpos($blob, 'japan post') !== false || strpos($blob, 'japanpost') !== false || strpos($blob, 'ems') !== false || strpos($blob, 'epacket') !== false || strpos($blob, 'yu-mail') !== false) {
        return 'https://trackings.post.japanpost.jp/services/srv/search/direct?reqCodeNo1=' . $t . '&locale=en';
    }
    if (strpos($blob, 'dhl') !== false) {
        return 'https://www.dhl.com/global-en/home/tracking.html?tracking-id=' . $t;
    }
    if (strpos($blob, 'fedex') !== false) {
        return 'https://www.fedex.com/fedextrack/?trknbr=' . $t;
    }
    if (strpos($blob, 'ups') !== false) {
        return 'https://www.ups.com/track?tracknum=' . $t;
    }
    if (strpos($blob, 'yamato') !== false || strpos($blob, 'kuroneko') !== false) {
        return 'https://toi.kuronekoyamato.co.jp/cgi-bin/tneko?number01=' . $t;
    }

    // Number-format auto-detect (when carrier info is missing)
    // FedEx: 12 or 15 digit
    if (preg_match('/^\d{12}$/', $tRaw) || preg_match('/^\d{15}$/', $tRaw)) {
        return 'https://www.fedex.com/fedextrack/?trknbr=' . $t;
    }
    // DHL: 10 digit number, or 11-char with letter prefix (eg "JD012345...")
    if (preg_match('/^\d{10,11}$/', $tRaw) || preg_match('/^[A-Z]{2}\d{9}$/', $tRaw)) {
        return 'https://www.dhl.com/global-en/home/tracking.html?tracking-id=' . $t;
    }
    // UPS: starts with "1Z"
    if (preg_match('/^1Z[A-Z0-9]{16}$/i', $tRaw)) {
        return 'https://www.ups.com/track?tracknum=' . $t;
    }
    // Japan Post EMS / international: 13-char with 2 letters + 9 digits + 2 letters (e.g. "EM123456789JP")
    if (preg_match('/^[A-Z]{2}\d{9}[A-Z]{2}$/i', $tRaw)) {
        return 'https://trackings.post.japanpost.jp/services/srv/search/direct?reqCodeNo1=' . $t . '&locale=en';
    }

    // Final fallback: 17track (multi-carrier aggregator)
    return 'https://t.17track.net/en#nums=' . $t;
}
$trackUrl = trackingUrl($carrier, $service, $tracking);

// Items table
$itemRows = '';
foreach ($items as $item) {
    $title = htmlspecialchars($item['title'] ?? '');
    $qty = intval($item['qty'] ?? 1);
    $itemRows .= "<tr>
        <td style='padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:14px;'>{$title}</td>
        <td style='padding:10px 12px;border-bottom:1px solid #f0f0f0;text-align:center;font-size:14px;'>{$qty}</td>
    </tr>";
}

$trackingEsc = htmlspecialchars($tracking);
$carrierEsc = htmlspecialchars($carrier ?: 'Japan Post');
$serviceEsc = htmlspecialchars($service);

$subject = "MegaCards.jp - Your Order Has Shipped! #{$orderNumber}";

$html = "<!DOCTYPE html>
<html>
<head><meta charset='utf-8'></head>
<body style='margin:0;padding:0;background:#f5f5f0;font-family:Arial,Helvetica,sans-serif;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background:#f5f5f0;padding:24px 0;'>
<tr><td align='center'>
<table width='600' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);'>

    <tr><td style='background:#0f0f23;padding:28px 32px;text-align:center;'>
        <h1 style='margin:0;font-size:24px;color:#ffd700;letter-spacing:1px;'>MegaCards<span style='color:#fff;'>.jp</span></h1>
        <p style='margin:6px 0 0;font-size:13px;color:rgba(255,255,255,0.6);'>Japanese TCG Boxes Direct from Japan</p>
    </td></tr>

    <tr><td style='padding:32px;'>
        <div style='background:#e0f2fe;border:1px solid #7dd3fc;border-radius:10px;padding:22px;margin:0 0 24px 0;text-align:center;'>
            <div style='font-size:42px;line-height:1;margin-bottom:8px;'>📦</div>
            <p style='font-size:18px;color:#075985;font-weight:700;margin:0 0 6px 0;'>Your Order Has Shipped!</p>
            <p style='font-size:13px;color:#555;margin:0;'>Your package is on its way from Japan.</p>
        </div>

        <h2 style='margin:0 0 12px;font-size:18px;color:#1a1a2e;'>Hi {$name},</h2>
        <p style='font-size:14px;color:#555;margin:0 0 16px;line-height:1.7;'>
            Great news! Your order <strong>#{$orderNumber}</strong> has been dispatched.
            You can track your shipment using the information below.
        </p>

        <!-- Tracking box -->
        <div style='background:#f8f8f8;border:2px solid #1a1a2e;border-radius:10px;padding:22px;margin:24px 0;text-align:center;'>
            <p style='font-size:12px;color:#888;margin:0 0 6px 0;text-transform:uppercase;letter-spacing:1px;'>Tracking Number</p>
            <p style='font-size:22px;color:#1a1a2e;font-weight:700;font-family:monospace;margin:0 0 4px 0;letter-spacing:1px;'>{$trackingEsc}</p>
            <p style='font-size:13px;color:#666;margin:0 0 16px 0;'>{$carrierEsc} {$serviceEsc}</p>
            <a href='{$trackUrl}' target='_blank' style='display:inline-block;padding:14px 36px;background:#ffd700;color:#1a1a2e;font-weight:700;border-radius:8px;text-decoration:none;font-size:15px;'>Track Package →</a>
        </div>

        <!-- Info box -->
        <div style='background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:18px;margin:20px 0;'>
            <p style='font-size:14px;color:#8d6e00;font-weight:700;margin:0 0 8px 0;'>ℹ️ Important</p>
            <ul style='margin:0;padding-left:20px;font-size:13px;color:#555;line-height:1.7;'>
                <li>Tracking information may take <strong>24–48 hours</strong> to appear online after dispatch.</li>
                <li>Delivery time varies by destination, typically <strong>5–14 business days</strong>.</li>
                <li>Import duties, GST, VAT, or customs fees (if any) are the buyer's responsibility upon delivery.</li>
                <li>Please ensure someone is available at the delivery address to sign for the package if required.</li>
                <li>This shipment is covered <strong>only by the carrier's standard insurance</strong>. No additional insurance is included.</li>
            </ul>
        </div>

        <!-- Items -->
        <table width='100%' cellpadding='0' cellspacing='0' style='border:1px solid #eee;border-radius:8px;overflow:hidden;margin:20px 0 8px;'>
            <tr style='background:#f8f8f8;'>
                <th style='padding:10px 12px;text-align:left;font-size:13px;color:#888;font-weight:600;'>Item Shipped</th>
                <th style='padding:10px 12px;text-align:center;font-size:13px;color:#888;font-weight:600;'>Qty</th>
            </tr>
            {$itemRows}
        </table>

        <p style='font-size:13px;color:#888;margin:20px 0 0;line-height:1.7;'>
            Questions? Reply to this email or contact
            <a href='mailto:info@atta-jp.com' style='color:#1a1a2e;'>info@atta-jp.com</a>.
        </p>
    </td></tr>

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

$adminTo = 'info@atta-trade.com';
if (strtolower(trim($to)) !== strtolower(trim($adminTo))) {
    $adminSubject = "[MegaCards] Shipped #{$orderNumber} - {$name} ({$tracking})";
    mail($adminTo, $adminSubject, $html, $headers);
}

if ($sent) {
    echo json_encode(['ok' => true, 'message' => 'Shipped notification email sent']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send email']);
}
