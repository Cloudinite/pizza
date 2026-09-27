<?php
defined('PS_APP') || exit;

/*
 * Admin authentication.
 * - Passwords: Argon2id (bcrypt where the server lacks it); old hashes upgrade on next login.
 * - Optional second step: 6-digit code from an authenticator app (TOTP) or a one-time backup code.
 * - Sessions live in the admin_sessions table (not PHP session files), so the installed app stays
 *   logged in for 30 days even on shared hosting. Cookie value "<selector>.<validator>";
 *   only a SHA-256 of the validator is stored, so a database leak cannot be replayed.
 * - Cookie: HttpOnly, Secure, SameSite=Lax and, on HTTPS, the __Host- prefix (cannot be set
 *   or overwritten by any subdomain or by plain HTTP).
 */

const ADMIN_SESSION_DAYS = 30;
const ADMIN_2FA_SECONDS = 300;

function admin_cookie_name(): string
{
    return is_https() ? '__Host-ps_admin' : 'ps_admin';
}

function ps_password_algo(): string|int|null
{
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
}

function ps_password_hash(string $password): string
{
    return password_hash($password, ps_password_algo());
}

/** Minimal password rules for the admin account; returns '' when fine. */
function ps_password_problem(string $password, string $username = ''): string
{
    if (strlen($password) < 10) {
        return 'Heslo musí mať aspoň 10 znakov.';
    }
    if ($username !== '' && stripos($password, $username) !== false) {
        return 'Heslo nesmie obsahovať prihlasovacie meno.';
    }
    if (preg_match('/^(.)\1+$/', $password) || in_array(strtolower($password), ['1234567890', 'heslo12345', 'password123', 'pizzaslice1', 'qwertyuiop'], true)) {
        return 'Toto heslo je príliš ľahké uhádnuť.';
    }
    return '';
}

function admin_current(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    $raw = $_COOKIE[admin_cookie_name()] ?? '';
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
    return $user = ['id' => (int) $row['user_id'], 'username' => $row['username'], 'selector' => $m[1], 'session_id' => (int) $row['id']];
}

function admin_set_cookie(string $value): void
{
    set_cookie(admin_cookie_name(), $value, $value === '' ? -1 : ADMIN_SESSION_DAYS * 86400, ['path' => '/', 'samesite' => 'Lax']);
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
 * Checks username + password with brute-force throttling.
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
    // Unknown user: still spend the same time hashing, so timing does not reveal valid usernames.
    $hash = $row['password_hash'] ?? ps_password_hash(bin2hex(random_bytes(12)));
    if (!password_verify($password, $hash) || !$row) {
        rate_record('login_ip', $ip);
        rate_record('login_user', $userKey);
        return [null, 'Nesprávne meno alebo heslo.'];
    }
    if (password_needs_rehash($hash, ps_password_algo())) {
        db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')->execute([ps_password_hash($password), $row['id']]);
    }
    return [(int) $row['id'], ''];
}

/* ---------- second step (2FA) between password and session ---------- */

/** Short-lived signed ticket "uid.expiry.sig" proving the password step passed. */
function admin_2fa_pending_set(int $userId): void
{
    $exp = time() + ADMIN_2FA_SECONDS;
    set_cookie('ps_2fa', $userId . '.' . $exp . '.' . hmac("2fa|$userId|$exp"), ADMIN_2FA_SECONDS, ['samesite' => 'Strict']);
}

function admin_2fa_pending(): ?int
{
    $raw = $_COOKIE['ps_2fa'] ?? '';
    if (!is_string($raw) || !preg_match('/^(\d{1,10})\.(\d{10})\.([a-f0-9]{64})$/', $raw, $m)) {
        return null;
    }
    if ((int) $m[2] < time() || !hash_equals(hmac("2fa|{$m[1]}|{$m[2]}"), $m[3])) {
        return null;
    }
    return (int) $m[1];
}

function admin_2fa_pending_clear(): void
{
    set_cookie('ps_2fa', '', -1, ['samesite' => 'Strict']);
}

function admin_user_row(int $userId): ?array
{
    $st = db()->prepare('SELECT * FROM admin_users WHERE id = ?');
    $st->execute([$userId]);
    return $st->fetch() ?: null;
}

function admin_totp_enabled(int $userId): bool
{
    $u = admin_user_row($userId);
    return $u && (int) ($u['totp_enabled'] ?? 0) === 1;
}

/**
 * Checks a 6-digit app code or a backup code (each backup code works once).
 * @return array{0: bool, 1: string}
 */
function admin_2fa_check(int $userId, string $input): array
{
    $key = hmac('2fa-user|' . $userId);
    if (!rate_allow('login_2fa', $key, 6, 300, false)) {
        return [false, 'Príliš veľa pokusov. Počkajte 5 minút.'];
    }
    $u = admin_user_row($userId);
    if (!$u || !(int) $u['totp_enabled']) {
        return [false, 'Overenie nie je zapnuté.'];
    }
    $input = strtolower(preg_replace('/[\s-]/', '', $input) ?? '');
    if (preg_match('/^\d{6}$/', $input)) {
        $secret = decrypt_str($u['totp_secret_enc']);
        $step = $secret !== null ? totp_verify(base32_decode($secret), $input, $u['totp_last_step'] !== null ? (int) $u['totp_last_step'] : null) : null;
        if ($step !== null) {
            // remember the used time-step: the same code cannot be replayed
            db()->prepare('UPDATE admin_users SET totp_last_step = ? WHERE id = ?')->execute([$step, $userId]);
            return [true, ''];
        }
    } elseif (preg_match('/^[a-z0-9]{10}$/', $input)) {
        $codes = json_decode((string) $u['backup_codes'], true) ?: [];
        $h = hmac('backup|' . $input);
        foreach ($codes as $i => $stored) {
            if (hash_equals($stored, $h)) {
                unset($codes[$i]);
                db()->prepare('UPDATE admin_users SET backup_codes = ? WHERE id = ?')->execute([json_encode(array_values($codes)), $userId]);
                return [true, ''];
            }
        }
    }
    rate_record('login_2fa', $key);
    return [false, 'Kód nie je správny. Skúste najnovší kód z aplikácie.'];
}

/** @return string[] fresh backup codes (shown once); only their HMACs are stored */
function admin_backup_codes_new(int $userId): array
{
    $abc = 'abcdefghjkmnpqrstuvwxyz23456789';
    $plain = [];
    for ($n = 0; $n < 8; $n++) {
        $c = '';
        for ($i = 0; $i < 10; $i++) {
            $c .= $abc[random_int(0, strlen($abc) - 1)];
        }
        $plain[] = $c;
    }
    db()->prepare('UPDATE admin_users SET backup_codes = ? WHERE id = ?')
        ->execute([json_encode(array_map(static fn ($c) => hmac('backup|' . $c), $plain)), $userId]);
    return array_map(static fn ($c) => substr($c, 0, 5) . '-' . substr($c, 5), $plain);
}

/* ---------- sessions (logged-in devices) ---------- */

function admin_sessions_list(int $userId): array
{
    $st = db()->prepare('SELECT id, selector, user_agent, created_at, last_seen_at FROM admin_sessions WHERE user_id = ? AND expires_at > ? ORDER BY last_seen_at DESC');
    $st->execute([$userId, now_str()]);
    return $st->fetchAll();
}

/** "iPhone · Safari" style label from a user-agent string. */
function device_label(string $ua): string
{
    $os = match (true) {
        (bool) preg_match('/iPhone/i', $ua) => 'iPhone',
        (bool) preg_match('/iPad/i', $ua) => 'iPad',
        (bool) preg_match('/Android/i', $ua) => 'Android',
        (bool) preg_match('/Windows/i', $ua) => 'Windows',
        (bool) preg_match('/Mac OS X|Macintosh/i', $ua) => 'Mac',
        (bool) preg_match('/Linux/i', $ua) => 'Linux',
        default => 'Neznáme zariadenie',
    };
    $browser = match (true) {
        (bool) preg_match('/Edg\//', $ua) => 'Edge',
        (bool) preg_match('/SamsungBrowser/', $ua) => 'Samsung Internet',
        (bool) preg_match('/OPR\//', $ua) => 'Opera',
        (bool) preg_match('/Firefox|FxiOS/', $ua) => 'Firefox',
        (bool) preg_match('/Chrome|CriOS/', $ua) => 'Chrome',
        (bool) preg_match('/Safari/', $ua) => 'Safari',
        default => '',
    };
    return $browser !== '' ? "$os · $browser" : $os;
}

function require_admin(bool $api = false): array
{
    $u = admin_current();
    if (!$u) {
        if ($api) {
            json_out(['error' => 'auth'], 401);
        }
        redirect(admin_url('prihlasenie'));
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
