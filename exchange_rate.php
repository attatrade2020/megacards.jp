<?php
declare(strict_types=1);
require_once __DIR__ . '/exchange_rate_lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$rate = mc_exchange_rate();
echo json_encode(['ok' => true, 'rate' => $rate['rate'], 'date' => $rate['date']], JSON_UNESCAPED_SLASHES);
