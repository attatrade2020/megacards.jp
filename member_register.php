<?php
declare(strict_types=1);

function megacards_member_response(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function megacards_member_validate(array $input): array
{
    $name = trim((string)($input['name'] ?? ''));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $password = (string)($input['password'] ?? '');
    $state = strtoupper(trim((string)($input['state'] ?? '')));
    $accepted = ($input['accepted_terms'] ?? false) === true;
    $states = ['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'ACT', 'NT'];

    if ($name === '' || mb_strlen($name) > 80) return ['error' => 'Enter your full name.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) return ['error' => 'Enter a valid email address.'];
    if (strlen($password) < 10 || strlen($password) > 128 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return ['error' => 'Use at least 10 characters with a letter and a number.'];
    }
    if (!in_array($state, $states, true)) return ['error' => 'Select your Australian state or territory.'];
    if (!$accepted) return ['error' => 'Accept the Terms and Privacy Policy to register.'];

    return [
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'state' => $state,
        'marketing' => ($input['marketing'] ?? false) === true,
    ];
}

function megacards_member_store(array $member, string $file): array
{
    $directory = dirname($file);
    if (!is_dir($directory) && !@mkdir($directory, 0755, true)) return ['error' => 'Registration storage is unavailable.'];
    $handle = @fopen($file, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) return ['error' => 'Registration storage is unavailable.'];

    try {
        rewind($handle);
        $raw = stream_get_contents($handle);
        $members = $raw !== false && trim($raw) !== '' ? json_decode($raw, true) : [];
        if (!is_array($members)) $members = [];
        foreach ($members as $existing) {
            if (is_array($existing) && strtolower((string)($existing['email'] ?? '')) === $member['email']) {
                return ['conflict' => true];
            }
        }

        $id = 'mem_' . bin2hex(random_bytes(10));
        $members[] = [
            'id' => $id,
            'name' => $member['name'],
            'email' => $member['email'],
            'password_hash' => password_hash($member['password'], PASSWORD_DEFAULT),
            'state' => $member['state'],
            'marketing' => $member['marketing'],
            'email_verified' => false,
            'status' => 'active',
            'created_at' => gmdate('c'),
        ];
        $encoded = json_encode($members, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($encoded)) return ['error' => 'Could not save the registration.'];
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $encoded . PHP_EOL) === false || !fflush($handle)) {
            return ['error' => 'Could not save the registration.'];
        }
        @chmod($file, 0640);
        return ['id' => $id];
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function megacards_member_rate_allowed(string $ip): bool
{
    $file = __DIR__ . '/data/member_rate_' . hash('sha256', $ip) . '.json';
    $now = time();
    $windowStart = $now - 3600;
    $handle = @fopen($file, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) return false;
    try {
        rewind($handle);
        $raw = stream_get_contents($handle);
        $attempts = $raw !== false ? json_decode($raw, true) : [];
        if (!is_array($attempts)) $attempts = [];
        $attempts = array_values(array_filter($attempts, static fn($value) => is_int($value) && $value >= $windowStart));
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

function megacards_handle_member_registration(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        megacards_member_response(405, ['error' => 'Method not allowed.']);
        return;
    }
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 20000) {
        megacards_member_response(413, ['error' => 'Request is too large.']);
        return;
    }
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    if ($origin !== '') {
        $originHost = strtolower((string)parse_url($origin, PHP_URL_HOST));
        if ($originHost === '' || $originHost !== $host) {
            megacards_member_response(403, ['error' => 'Origin not allowed.']);
            return;
        }
    }
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) {
        megacards_member_response(400, ['error' => 'Invalid registration request.']);
        return;
    }
    if (trim((string)($input['website'] ?? '')) !== '') {
        megacards_member_response(201, ['ok' => true]);
        return;
    }
    $ip = filter_var((string)($_SERVER['REMOTE_ADDR'] ?? ''), FILTER_VALIDATE_IP) ?: 'unknown';
    if (!megacards_member_rate_allowed($ip)) {
        megacards_member_response(429, ['error' => 'Too many registration attempts. Try again later.']);
        return;
    }
    $validated = megacards_member_validate($input);
    if (isset($validated['error'])) {
        megacards_member_response(422, $validated);
        return;
    }
    $stored = megacards_member_store($validated, __DIR__ . '/data/members.json');
    if (!empty($stored['conflict'])) {
        megacards_member_response(409, ['error' => 'An account already exists for this email address.']);
        return;
    }
    if (isset($stored['error'])) {
        megacards_member_response(500, ['error' => $stored['error']]);
        return;
    }
    megacards_member_response(201, ['ok' => true, 'member_id' => $stored['id']]);
}

if (!defined('MEGACARDS_REGISTER_LIBRARY_ONLY')) {
    megacards_handle_member_registration();
}
