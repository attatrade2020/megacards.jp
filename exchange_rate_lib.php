<?php
declare(strict_types=1);

function mc_exchange_rate(): array
{
    $fallback = ['rate' => 110.04, 'date' => '2026-10-08', 'source' => 'fallback'];
    $file = __DIR__ . '/data/aud_jpy_rate.json';
    if (is_file($file)) {
        $cached = json_decode((string) file_get_contents($file), true);
        if (is_array($cached) && (float) ($cached['rate'] ?? 0) > 0 && time() - (int) ($cached['fetched_at'] ?? 0) < 21600) return $cached;
    }
    $ch = curl_init('https://api.frankfurter.app/latest?from=AUD&to=JPY');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $data = is_string($raw) ? json_decode($raw, true) : [];
    $rate = is_array($data) ? (float) ($data['rates']['JPY'] ?? 0) : 0;
    if ($rate > 50 && $rate < 200) {
        $result = ['rate' => round($rate, 4), 'date' => (string) ($data['date'] ?? gmdate('Y-m-d')), 'source' => 'Frankfurter', 'fetched_at' => time()];
        @file_put_contents($file, json_encode($result, JSON_UNESCAPED_SLASHES), LOCK_EX);
        @chmod($file, 0640);
        return $result;
    }
    if (is_file($file)) {
        $cached = json_decode((string) file_get_contents($file), true);
        if (is_array($cached) && (float) ($cached['rate'] ?? 0) > 0) return $cached;
    }
    $fallback['fetched_at'] = time();
    return $fallback;
}

function mc_aud_jpy_rate(): float
{
    return (float) mc_exchange_rate()['rate'];
}
