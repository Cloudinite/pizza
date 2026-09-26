<?php
defined('PS_APP') || exit;

/*
 * Admin authentication. Sessions live in the admin_sessions table (not PHP session files),
 * so the installed admin app stays logged in for 30 days even on shared hosting where
 * PHP's session garbage collector would log you out after ~24 minutes.
 * Cookie value: "<selector>.<validator>"; only a SHA-256 of the validator is stored.
 */

const ADMIN_COOKIE = 'ps_adm';
const ADMIN_SESSION_DAYS = 30;

function admin_current(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    $raw = $_COOKIE[ADMIN_COOKIE] ?? '';
    if (!is_string($raw) || !preg_match('/^([a-f0-9]{24})\.([a-f0-9]{64})$/', $raw, $m)) {
        return null;
    }
    $st = db()->prepare(
        'SELECT s.id, s.user_id, s.validator_hash, s.expires_at, s.last_seen_at, u.username
           FROM admin_sessions s JOIN admin_users u ON u.id = s.user_id WHERE s.selector = ?'
    );
    $st->execute([$m[1]]);
    $row = $st->fetch();
    if (!$row || !hash_equals($row['validator_hash'], hash('sha256', $m[2])) || $row['expires_at'] < now_str()) {
        return null;
    }
    // Sliding expiry, written at most once per hour.
    if (strtotime($row['last_seen_at']) < time() - 3600) {
        db()->prepare('UPDATE admin_sessions SET last_seen_at = ?, expires_at = ? WHERE id = ?')
            ->execute([now_str(), date('Y-m-d H:i:s', time() + ADMIN_SESSION_DAYS * 86400), $row['id']]);
        admin_set_cookie($raw);
    }
    return $user = ['id' => (int) $row['user_id'], 'username' => $row['username'], 'selector' => $m[1]];
}

function admin_set_cookie(string $value): void
{
    set_cookie(ADMIN_COOKIE, $value, $value === '' ? -1 : ADMIN_SESSION_DAYS * 86400, ['path' => '/admin/', 'samesite' => 'Lax']);
}

function admin_login(int $userId): void
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $now = now_str();
    db()->prepare(
        'INSERT INTO admin_sessions (selector, validator_hash, user_id, user_agent, created_at, last_seen_at, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $selector, hash('sha256', $validator), $userId, mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
        $now, $now, date('Y-m-d H:i:s', time() + ADMIN_SESSION_DAYS * 86400),
    ]);
    db()->prepare('UPDATE admin_users SET last_login_at = ? WHERE id = ?')->execute([$now, $userId]);
    admin_set_cookie($selector . '.' . $validator);
}

function admin_logout(): void
{
    $u = admin_current();
    if ($u) {
        db()->prepare('DELETE FROM admin_sessions WHERE selector = ?')->execute([$u['selector']]);
    }
    admin_set_cookie('');
}

/**
 * Checks credentials with brute-force throttling.
 * @return array{0: ?int, 1: string} [user id or null, error message]
 */
function admin_attempt(string $username, string $password): array
{
    $ip = ip_ident();
    $userKey = hmac('user|' . mb_strtolower($username));
    if (!rate_allow('login_ip', $ip, 10, 900, false) || !rate_allow('login_user', $userKey, 20, 900, false)) {
        return [null, 'Príliš veľa neúspešných pokusov. Skúste to znova o 15 minút.'];
    }
    $st = db()->prepare('SELECT id, password_hash FROM admin_users WHERE username = ?');
    $st->execute([$username]);
    $row = $st->fetch();
    // Verify against a dummy hash when the user does not exist, so timing does not reveal usernames.
    $hash = $row['password_hash'] ?? '$2y$12$VaiwgfqgjtEE7KDtjuDBkuigsgeXKnYGoTLjHeO/ofHGVyqSTEi7y';
    if (!password_verify($password, $hash) || !$row) {
        rate_record('login_ip', $ip);
        rate_record('login_user', $userKey);
        return [null, 'Nesprávne meno alebo heslo.'];
    }
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }
    return [(int) $row['id'], ''];
}

function require_admin(bool $api = false): array
{
    $u = admin_current();
    if (!$u) {
        if ($api) {
            json_out(['error' => 'auth'], 401);
        }
        redirect('/admin/login.php');
    }
    return $u;
}

function admin_csrf(): string
{
    $u = admin_current();
    return $u ? hmac('adm|' . $u['selector']) : '';
}

function admin_csrf_ok(): bool
{
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && admin_csrf() !== '' && hash_equals(admin_csrf(), $sent) && same_origin_request();
}

function admin_require_csrf(bool $api = false): void
{
    if (!admin_csrf_ok()) {
        if ($api) {
            json_out(['error' => 'csrf'], 403);
        }
        http_response_code(403);
        exit('Neplatný bezpečnostný token. Obnovte stránku a skúste znova.');
    }
}
