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
    $phone = trim((string)($input['phone'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $addressLine1 = trim((string)($input['address_line1'] ?? ''));
    $addressLine2 = trim((string)($input['address_line2'] ?? ''));
    $suburb = trim((string)($input['suburb'] ?? ''));
    $state = strtoupper(trim((string)($input['state'] ?? '')));
    $postcode = trim((string)($input['postcode'] ?? ''));
    $accepted = ($input['accepted_terms'] ?? false) === true;
    $states = ['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'ACT', 'NT'];

    if ($name === '' || mb_strlen($name) > 80) return ['error' => 'Enter your full name.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) return ['error' => 'Enter a valid email address.'];
    $phoneDigits = preg_replace('/\D/', '', $phone) ?? '';
    if (!preg_match('/^[+0-9][0-9 ()-]{7,24}$/', $phone) || strlen($phoneDigits) < 8 || strlen($phoneDigits) > 15) return ['error' => 'Enter a valid phone number, including the country code when applicable.'];
    if (strlen($password) < 8 || strlen($password) > 128 || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
        return ['error' => 'Use at least 8 characters with an uppercase letter and a number.'];
    }
    if ($addressLine1 === '' || mb_strlen($addressLine1) > 120) return ['error' => 'Enter your street address.'];
    if (mb_strlen($addressLine2) > 120) return ['error' => 'Address line 2 is too long.'];
    if ($suburb === '' || mb_strlen($suburb) > 80) return ['error' => 'Enter your suburb or city.'];
    if (!in_array($state, $states, true)) return ['error' => 'Select your Australian state or territory.'];
    if (!preg_match('/^\d{4}$/', $postcode)) return ['error' => 'Enter a valid 4-digit Australian postcode.'];
    if (!$accepted) return ['error' => 'Accept the Terms and Privacy Policy to register.'];

    return [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'password' => $password,
        'state' => $state,
        'address' => [
            'line1' => $addressLine1,
            'line2' => $addressLine2,
            'suburb' => $suburb,
            'state' => $state,
            'postcode' => $postcode,
            'country' => 'Australia',
            'country_code' => 'AU',
        ],
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
            'phone' => $member['phone'],
            'password_hash' => password_hash($member['password'], PASSWORD_DEFAULT),
            'state' => $member['state'],
            'address' => $member['address'],
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

function megacards_member_login_after_registration(array $member, string $id): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $ttl = 2592000;
        ini_set('session.gc_maxlifetime', (string) $ttl);
        session_set_cookie_params([
            'lifetime' => $ttl,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
    session_regenerate_id(true);
    $_SESSION['mc_member'] = [
        'id' => $id,
        'name' => $member['name'],
        'email' => $member['email'],
        'phone' => $member['phone'],
        'state' => $member['state'],
        'address' => $member['address'],
        'authenticated_at' => time(),
    ];
    return [
        'id' => $id,
        'name' => $member['name'],
        'email' => $member['email'],
        'phone' => $member['phone'],
        'state' => $member['state'],
        'address' => $member['address'],
    ];
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
    $publicMember = megacards_member_login_after_registration($validated, $stored['id']);
    megacards_member_response(201, ['ok' => true, 'authenticated' => true, 'member_id' => $stored['id'], 'member' => $publicMember]);
}

if (!defined('MEGACARDS_REGISTER_LIBRARY_ONLY')) {
    megacards_handle_member_registration();
}
