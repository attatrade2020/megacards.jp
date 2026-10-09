<?php
declare(strict_types=1);

function reset_response(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function reset_origin_allowed(): bool
{
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin === '') return true;
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
    return $originHost !== '' && hash_equals($host, $originHost);
}

function reset_rate_allowed(string $ip): bool
{
    $file = __DIR__ . '/data/member_reset_rate_' . hash('sha256', $ip) . '.json';
    $now = time();
    $windowStart = $now - 3600;
    $handle = @fopen($file, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) return false;
    try {
        rewind($handle);
        $attempts = json_decode((string) stream_get_contents($handle), true);
        if (!is_array($attempts)) $attempts = [];
        $attempts = array_values(array_filter($attempts, static fn($value): bool => is_int($value) && $value >= $windowStart));
        if (count($attempts) >= 5) return false;
        $attempts[] = $now;
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($attempts));
        fflush($handle);
        @chmod($file, 0640);
        return true;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function reset_read_json(string $file): array
{
    if (!is_file($file)) return [];
    $decoded = json_decode((string) file_get_contents($file), true);
    return is_array($decoded) ? $decoded : [];
}

function reset_store_token(string $email, string $tokenHash, string $file): bool
{
    $handle = @fopen($file, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) return false;
    try {
        rewind($handle);
        $records = json_decode((string) stream_get_contents($handle), true);
        if (!is_array($records)) $records = [];
        $now = time();
        $records = array_values(array_filter($records, static fn($record): bool => is_array($record) && (int) ($record['expires_at'] ?? 0) > $now && empty($record['used_at']) && strtolower((string) ($record['email'] ?? '')) !== $email));
        $records[] = ['email' => $email, 'token_hash' => $tokenHash, 'expires_at' => $now + 3600, 'created_at' => gmdate('c')];
        $encoded = json_encode($records, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($encoded)) return false;
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $encoded . PHP_EOL) === false || !fflush($handle)) return false;
        @chmod($file, 0640);
        return true;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function reset_apply_password(string $token, string $password, string $tokensFile, string $membersFile): bool
{
    $tokenHash = hash('sha256', $token);
    $tokenHandle = @fopen($tokensFile, 'c+');
    if (!$tokenHandle || !flock($tokenHandle, LOCK_EX)) return false;
    try {
        rewind($tokenHandle);
        $records = json_decode((string) stream_get_contents($tokenHandle), true);
        if (!is_array($records)) $records = [];
        $matchIndex = null;
        $email = '';
        foreach ($records as $index => $record) {
            if (!is_array($record) || !empty($record['used_at']) || (int) ($record['expires_at'] ?? 0) < time()) continue;
            if (hash_equals((string) ($record['token_hash'] ?? ''), $tokenHash)) {
                $matchIndex = $index;
                $email = strtolower((string) ($record['email'] ?? ''));
                break;
            }
        }
        if ($matchIndex === null || !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

        $memberHandle = @fopen($membersFile, 'c+');
        if (!$memberHandle || !flock($memberHandle, LOCK_EX)) return false;
        try {
            rewind($memberHandle);
            $members = json_decode((string) stream_get_contents($memberHandle), true);
            if (!is_array($members)) return false;
            $memberIndex = null;
            foreach ($members as $index => $member) {
                if (is_array($member) && strtolower((string) ($member['email'] ?? '')) === $email && ($member['status'] ?? 'active') === 'active') {
                    $memberIndex = $index;
                    break;
                }
            }
            if ($memberIndex === null) return false;
            $members[$memberIndex]['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $members[$memberIndex]['updated_at'] = gmdate('c');
            $memberJson = json_encode($members, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if (!is_string($memberJson)) return false;
            rewind($memberHandle);
            if (!ftruncate($memberHandle, 0) || fwrite($memberHandle, $memberJson . PHP_EOL) === false || !fflush($memberHandle)) return false;
            @chmod($membersFile, 0640);
        } finally {
            flock($memberHandle, LOCK_UN);
            fclose($memberHandle);
        }

        $records[$matchIndex]['used_at'] = gmdate('c');
        $tokenJson = json_encode($records, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (is_string($tokenJson)) {
            rewind($tokenHandle);
            ftruncate($tokenHandle, 0);
            fwrite($tokenHandle, $tokenJson . PHP_EOL);
            fflush($tokenHandle);
        }
        return true;
    } finally {
        flock($tokenHandle, LOCK_UN);
        fclose($tokenHandle);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    reset_response(405, ['error' => 'Method not allowed.']);
}
if (!reset_origin_allowed()) reset_response(403, ['error' => 'Origin not allowed.']);
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 10000) reset_response(413, ['error' => 'Request is too large.']);
$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) reset_response(400, ['error' => 'Invalid reset request.']);

$ip = filter_var((string) ($_SERVER['REMOTE_ADDR'] ?? ''), FILTER_VALIDATE_IP) ?: 'unknown';
if (!reset_rate_allowed($ip)) reset_response(429, ['error' => 'Too many reset attempts. Try again later.']);
$action = (string) ($input['action'] ?? 'request');
$membersFile = __DIR__ . '/data/members.json';
$tokensFile = __DIR__ . '/data/member_password_resets.json';

if ($action === 'request') {
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) reset_response(422, ['error' => 'Enter a valid email address.']);
    $members = reset_read_json($membersFile);
    $exists = false;
    foreach ($members as $member) {
        if (is_array($member) && strtolower((string) ($member['email'] ?? '')) === $email && ($member['status'] ?? 'active') === 'active') {
            $exists = true;
            break;
        }
    }
    if ($exists) {
        $token = bin2hex(random_bytes(32));
        if (reset_store_token($email, hash('sha256', $token), $tokensFile)) {
            $link = 'https://megacards.jp/reset-password.php?token=' . rawurlencode($token);
            $subject = 'Reset your MegaCards password';
            $message = "A password reset was requested for your MegaCards account.\n\nReset your password using this secure link:\n{$link}\n\nThis link expires in 60 minutes and can be used once. If you did not request this, you can ignore this email.\n\nMegaCards.jp\n";
            $headers = "From: MegaCards.jp <info@megacards.jp>\r\nReply-To: info@megacards.jp\r\nContent-Type: text/plain; charset=UTF-8";
            @mail($email, $subject, $message, $headers);
        }
    } else {
        usleep(250000);
    }
    reset_response(200, ['ok' => true, 'message' => 'If that email is registered, a reset link has been sent. Please also check your spam folder.']);
}

if ($action === 'reset') {
    $token = strtolower(trim((string) ($input['token'] ?? '')));
    $password = (string) ($input['password'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) reset_response(422, ['error' => 'This reset link is invalid or has expired.']);
    if (strlen($password) < 8 || strlen($password) > 128 || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
        reset_response(422, ['error' => 'Use at least 8 characters with an uppercase letter and a number.']);
    }
    if (!reset_apply_password($token, $password, $tokensFile, $membersFile)) {
        reset_response(422, ['error' => 'This reset link is invalid or has expired. Request a new link and try again.']);
    }
    reset_response(200, ['ok' => true, 'message' => 'Your password has been updated. You can now log in.']);
}

reset_response(400, ['error' => 'Unknown action.']);
