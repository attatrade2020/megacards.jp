<?php
declare(strict_types=1);

const MEGACARDS_MEMBER_SESSION_TTL = 2592000;

function megacards_auth_response(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function megacards_member_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.gc_maxlifetime', (string) MEGACARDS_MEMBER_SESSION_TTL);
    session_set_cookie_params([
        'lifetime' => MEGACARDS_MEMBER_SESSION_TTL,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function megacards_member_public_session(): ?array
{
    $member = $_SESSION['mc_member'] ?? null;
    if (!is_array($member) || empty($member['id']) || empty($member['email'])) return null;
    return [
        'id' => (string) $member['id'],
        'name' => (string) ($member['name'] ?? ''),
        'email' => (string) $member['email'],
        'state' => (string) ($member['state'] ?? ''),
        'address' => is_array($member['address'] ?? null) ? $member['address'] : [],
    ];
}

function megacards_auth_origin_allowed(): bool
{
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin === '') return true;
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
    return $originHost !== '' && hash_equals($host, $originHost);
}

function megacards_login_rate_allowed(string $ip): bool
{
    $file = __DIR__ . '/data/member_login_rate_' . hash('sha256', $ip) . '.json';
    $now = time();
    $windowStart = $now - 900;
    $handle = @fopen($file, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) return false;
    try {
        rewind($handle);
        $raw = stream_get_contents($handle);
        $attempts = $raw !== false ? json_decode($raw, true) : [];
        if (!is_array($attempts)) $attempts = [];
        $attempts = array_values(array_filter($attempts, static fn($value): bool => is_int($value) && $value >= $windowStart));
        if (count($attempts) >= 10) return false;
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

function megacards_update_member_profile(array $input, string $membersFile): array
{
    $sessionMember = megacards_member_public_session();
    if ($sessionMember === null) return ['status' => 401, 'error' => 'Please log in again.'];

    $name = trim((string) ($input['name'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $addressLine1 = trim((string) ($input['address_line1'] ?? ''));
    $addressLine2 = trim((string) ($input['address_line2'] ?? ''));
    $suburb = trim((string) ($input['suburb'] ?? ''));
    $state = strtoupper(trim((string) ($input['state'] ?? '')));
    $postcode = trim((string) ($input['postcode'] ?? ''));
    $currentPassword = (string) ($input['current_password'] ?? '');
    $newPassword = (string) ($input['new_password'] ?? '');
    $states = ['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'ACT', 'NT'];

    if ($name === '' || mb_strlen($name) > 80) return ['status' => 422, 'error' => 'Enter your full name.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) return ['status' => 422, 'error' => 'Enter a valid email address.'];
    if ($addressLine1 === '' || mb_strlen($addressLine1) > 120) return ['status' => 422, 'error' => 'Enter your street address.'];
    if (mb_strlen($addressLine2) > 120) return ['status' => 422, 'error' => 'Address line 2 is too long.'];
    if ($suburb === '' || mb_strlen($suburb) > 80) return ['status' => 422, 'error' => 'Enter your suburb or city.'];
    if (!in_array($state, $states, true)) return ['status' => 422, 'error' => 'Select your Australian state or territory.'];
    if (!preg_match('/^\d{4}$/', $postcode)) return ['status' => 422, 'error' => 'Enter a valid 4-digit Australian postcode.'];
    if ($currentPassword === '' || strlen($currentPassword) > 128) return ['status' => 422, 'error' => 'Enter your current password to save changes.'];
    if ($newPassword !== '' && (strlen($newPassword) < 8 || strlen($newPassword) > 128 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/\d/', $newPassword))) {
        return ['status' => 422, 'error' => 'Use at least 8 characters with an uppercase letter and a number for the new password.'];
    }

    $handle = @fopen($membersFile, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) return ['status' => 500, 'error' => 'Member information is temporarily unavailable.'];
    try {
        rewind($handle);
        $raw = stream_get_contents($handle);
        $members = $raw !== false && trim($raw) !== '' ? json_decode($raw, true) : [];
        if (!is_array($members)) return ['status' => 500, 'error' => 'Member information is temporarily unavailable.'];

        $memberIndex = null;
        foreach ($members as $index => $member) {
            if (!is_array($member)) continue;
            if ((string) ($member['id'] ?? '') === $sessionMember['id']) $memberIndex = $index;
            if ((string) ($member['id'] ?? '') !== $sessionMember['id'] && strtolower((string) ($member['email'] ?? '')) === $email) {
                return ['status' => 409, 'error' => 'An account already exists for this email address.'];
            }
        }
        if ($memberIndex === null || ($members[$memberIndex]['status'] ?? 'active') !== 'active') {
            return ['status' => 401, 'error' => 'Please log in again.'];
        }
        if (!password_verify($currentPassword, (string) ($members[$memberIndex]['password_hash'] ?? ''))) {
            usleep(250000);
            return ['status' => 401, 'error' => 'Current password is incorrect.'];
        }

        $members[$memberIndex]['name'] = $name;
        $members[$memberIndex]['email'] = $email;
        $members[$memberIndex]['state'] = $state;
        $members[$memberIndex]['address'] = [
            'line1' => $addressLine1,
            'line2' => $addressLine2,
            'suburb' => $suburb,
            'state' => $state,
            'postcode' => $postcode,
            'country' => 'Australia',
            'country_code' => 'AU',
        ];
        $members[$memberIndex]['updated_at'] = gmdate('c');
        if ($newPassword !== '') $members[$memberIndex]['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);

        $encoded = json_encode($members, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($encoded)) return ['status' => 500, 'error' => 'Could not save your member information.'];
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $encoded . PHP_EOL) === false || !fflush($handle)) {
            return ['status' => 500, 'error' => 'Could not save your member information.'];
        }
        @chmod($membersFile, 0640);
        $_SESSION['mc_member'] = [
            'id' => $sessionMember['id'],
            'name' => $name,
            'email' => $email,
            'state' => $state,
            'address' => $members[$memberIndex]['address'],
            'authenticated_at' => time(),
        ];
        session_regenerate_id(true);
        return ['status' => 200, 'member' => megacards_member_public_session()];
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

megacards_member_session_start();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $member = megacards_member_public_session();
    megacards_auth_response(200, ['authenticated' => $member !== null, 'member' => $member]);
    exit;
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    megacards_auth_response(405, ['error' => 'Method not allowed.']);
    exit;
}

if (!megacards_auth_origin_allowed()) {
    megacards_auth_response(403, ['error' => 'Origin not allowed.']);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    megacards_auth_response(400, ['error' => 'Invalid login request.']);
    exit;
}

$action = (string) ($input['action'] ?? 'login');
if ($action === 'logout') {
    unset($_SESSION['mc_member']);
    session_regenerate_id(true);
    megacards_auth_response(200, ['ok' => true, 'authenticated' => false]);
    exit;
}

if ($action === 'update_profile') {
    $ip = filter_var((string) ($_SERVER['REMOTE_ADDR'] ?? ''), FILTER_VALIDATE_IP) ?: 'unknown';
    if (!megacards_login_rate_allowed($ip)) {
        megacards_auth_response(429, ['error' => 'Too many attempts. Try again in 15 minutes.']);
        exit;
    }
    $result = megacards_update_member_profile($input, __DIR__ . '/data/members.json');
    $status = (int) ($result['status'] ?? 500);
    if (isset($result['error'])) {
        megacards_auth_response($status, ['error' => $result['error']]);
        exit;
    }
    megacards_auth_response(200, ['ok' => true, 'authenticated' => true, 'member' => $result['member']]);
    exit;
}

if ($action !== 'login') {
    megacards_auth_response(400, ['error' => 'Unknown action.']);
    exit;
}

$email = strtolower(trim((string) ($input['email'] ?? '')));
$password = (string) ($input['password'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || strlen($password) > 128) {
    megacards_auth_response(422, ['error' => 'Enter your email address and password.']);
    exit;
}

$ip = filter_var((string) ($_SERVER['REMOTE_ADDR'] ?? ''), FILTER_VALIDATE_IP) ?: 'unknown';
if (!megacards_login_rate_allowed($ip)) {
    megacards_auth_response(429, ['error' => 'Too many login attempts. Try again in 15 minutes.']);
    exit;
}

$membersFile = __DIR__ . '/data/members.json';
$members = is_file($membersFile) ? json_decode((string) file_get_contents($membersFile), true) : [];
if (!is_array($members)) $members = [];
$matched = null;
foreach ($members as $member) {
    if (!is_array($member)) continue;
    if (strtolower((string) ($member['email'] ?? '')) !== $email) continue;
    if (($member['status'] ?? 'active') !== 'active') break;
    if (password_verify($password, (string) ($member['password_hash'] ?? ''))) $matched = $member;
    break;
}

if ($matched === null) {
    usleep(250000);
    megacards_auth_response(401, ['error' => 'Email address or password is incorrect.']);
    exit;
}

session_regenerate_id(true);
$_SESSION['mc_member'] = [
    'id' => (string) $matched['id'],
    'name' => (string) ($matched['name'] ?? ''),
    'email' => (string) $matched['email'],
    'state' => (string) ($matched['state'] ?? ''),
    'address' => is_array($matched['address'] ?? null) ? $matched['address'] : [],
    'authenticated_at' => time(),
];
$publicMember = megacards_member_public_session();
megacards_auth_response(200, ['ok' => true, 'authenticated' => true, 'member' => $publicMember]);
