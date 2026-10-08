<?php
declare(strict_types=1);

const MEGACARDS_GATE_COUNTRY = 'JP';
const MEGACARDS_GATE_SALT = 'bbfb421708ae4af574d445beccb654a6';
const MEGACARDS_GATE_HASH = '05e6aac86f7cc5e3cf880773df5828df8b5f47120be7cae93c30e2792a577b79';
const MEGACARDS_GATE_ITERATIONS = 200000;
const MEGACARDS_GATE_SESSION_SECONDS = 43200;
const MEGACARDS_GEO_CACHE_SECONDS = 604800;

function megacards_client_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

function megacards_fetch_url(string $url): ?string
{
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'MegaCards.jp access control/1.0',
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return is_string($body) && $status >= 200 && $status < 300 ? $body : null;
}

function megacards_country_for_ip(string $ip): string
{
    if ($ip === '') return 'UNKNOWN';

    $cacheDir = __DIR__ . '/data/geo_cache';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
    $cacheFile = $cacheDir . '/' . hash('sha256', $ip) . '.json';
    if (is_file($cacheFile) && (time() - (int)filemtime($cacheFile)) < MEGACARDS_GEO_CACHE_SECONDS) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        $country = strtoupper((string)($cached['country'] ?? ''));
        if (preg_match('/^[A-Z]{2}$/', $country)) return $country;
    }

    $encodedIp = rawurlencode($ip);
    $country = '';

    $primary = megacards_fetch_url("https://ipapi.co/{$encodedIp}/country/");
    if (is_string($primary)) $country = strtoupper(trim($primary));

    if (!preg_match('/^[A-Z]{2}$/', $country)) {
        $fallback = megacards_fetch_url("https://ipwho.is/{$encodedIp}?fields=success,country_code");
        $decoded = is_string($fallback) ? json_decode($fallback, true) : null;
        if (is_array($decoded) && ($decoded['success'] ?? false)) {
            $country = strtoupper((string)($decoded['country_code'] ?? ''));
        }
    }

    if (!preg_match('/^[A-Z]{2}$/', $country)) return 'UNKNOWN';
    if (is_dir($cacheDir)) {
        @file_put_contents($cacheFile, json_encode(['country' => $country, 'checked_at' => time()]), LOCK_EX);
        @chmod($cacheFile, 0640);
    }
    return $country;
}

function megacards_start_gate_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('megacards_access');
    session_set_cookie_params([
        'lifetime' => MEGACARDS_GATE_SESSION_SECONDS,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function megacards_password_matches(string $password): bool
{
    $candidate = hash_pbkdf2('sha256', $password, MEGACARDS_GATE_SALT, MEGACARDS_GATE_ITERATIONS, 64);
    return hash_equals(MEGACARDS_GATE_HASH, $candidate);
}

function megacards_gate_headers(): void
{
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('CDN-Cache-Control: no-store');
    header('Surrogate-Control: no-store');
    header('Vary: Cookie', false);
}

function megacards_gate_form(string $error = ''): void
{
    if (empty($_SESSION['gate_csrf'])) $_SESSION['gate_csrf'] = bin2hex(random_bytes(24));
    header('Content-Type: text/html; charset=utf-8');
    megacards_gate_headers();
    header('X-Robots-Tag: noindex, nofollow');
    $csrf = htmlspecialchars((string)$_SESSION['gate_csrf'], ENT_QUOTES, 'UTF-8');
    $message = $error !== '' ? '<p class="error" role="alert">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>' : '';
    echo <<<HTML
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Access required | MegaCards.jp</title>
  <style>
    :root{color-scheme:light;--navy:#0b1428;--blue:#1459e9;--yellow:#ffd447;--line:#dce2eb;--soft:#586072}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;color:var(--navy);background:linear-gradient(145deg,#0b1428,#13264c);font-family:Arial,sans-serif}
    main{width:min(100%,440px);padding:34px;background:#fff;border-radius:22px;box-shadow:0 28px 80px rgba(0,0,0,.35)}
    .brand{display:flex;align-items:center;gap:10px;margin-bottom:30px;font-size:1.22rem;font-weight:800}.mark{display:grid;place-items:center;width:36px;height:36px;background:var(--yellow);border-radius:10px}.jp{margin:0 0 8px;color:var(--blue);font-size:.74rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
    h1{margin:0;font-size:1.75rem;line-height:1.15}p{color:var(--soft);line-height:1.65}.field{display:block;margin-top:24px}.field span{display:block;margin-bottom:7px;font-size:.76rem;font-weight:800}
    input{width:100%;min-height:50px;padding:11px 13px;border:1px solid var(--line);border-radius:10px;font-size:1rem}input:focus{outline:3px solid rgba(20,89,233,.13);border-color:var(--blue)}
    button{width:100%;min-height:52px;margin-top:14px;border:0;border-radius:11px;color:var(--navy);background:var(--yellow);font-size:.95rem;font-weight:800;cursor:pointer}.error{padding:10px 12px;color:#8d1b1b;background:#fff0f0;border:1px solid #efb4b4;border-radius:9px;font-size:.82rem}
    small{display:block;margin-top:18px;color:#7a8291;font-size:.72rem;line-height:1.5;text-align:center}
  </style>
</head>
<body>
  <main>
    <div class="brand"><span class="mark">M</span><span>MegaCards.jp</span></div>
    <p class="jp">Japan access</p>
    <h1>パスワードを入力してください</h1>
    <p>このオーストラリア向けストアは、日本国内からのアクセス時に閲覧パスワードが必要です。</p>
    {$message}
    <form method="post">
      <input type="hidden" name="gate_csrf" value="{$csrf}">
      <label class="field"><span>閲覧パスワード</span><input type="password" name="gate_password" autocomplete="current-password" required autofocus></label>
      <button type="submit">ストアを表示</button>
    </form>
    <small>認証後、このブラウザーでは12時間アクセスできます。</small>
  </main>
</body>
</html>
HTML;
    exit;
}

function megacards_render_protected_page(string $file): void
{
    if (!is_file($file)) {
        http_response_code(404);
        exit('Page not found');
    }

    megacards_gate_headers();
    $country = megacards_country_for_ip(megacards_client_ip());
    if ($country !== MEGACARDS_GATE_COUNTRY && $country !== 'UNKNOWN') {
        header('Content-Type: text/html; charset=utf-8');
        readfile($file);
        exit;
    }

    megacards_start_gate_session();
    $authenticatedAt = (int)($_SESSION['gate_authenticated_at'] ?? 0);
    if ($authenticatedAt > 0 && (time() - $authenticatedAt) < MEGACARDS_GATE_SESSION_SECONDS) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($file);
        exit;
    }

    $lockedUntil = (int)($_SESSION['gate_locked_until'] ?? 0);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($lockedUntil > time()) megacards_gate_form('試行回数が多すぎます。数分後にもう一度お試しください。');
        $csrf = (string)($_POST['gate_csrf'] ?? '');
        $password = (string)($_POST['gate_password'] ?? '');
        if (!hash_equals((string)($_SESSION['gate_csrf'] ?? ''), $csrf)) {
            megacards_gate_form('ページを再読み込みして、もう一度お試しください。');
        }
        if (megacards_password_matches($password)) {
            session_regenerate_id(true);
            $_SESSION['gate_authenticated_at'] = time();
            $_SESSION['gate_attempts'] = 0;
            unset($_SESSION['gate_locked_until']);
            $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
            header('Location: ' . $path, true, 303);
            exit;
        }
        $_SESSION['gate_attempts'] = (int)($_SESSION['gate_attempts'] ?? 0) + 1;
        if ($_SESSION['gate_attempts'] >= 5) {
            $_SESSION['gate_attempts'] = 0;
            $_SESSION['gate_locked_until'] = time() + 300;
        }
        megacards_gate_form('パスワードが正しくありません。');
    }

    megacards_gate_form();
}
