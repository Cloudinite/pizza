<?php
defined('PS_APP') || exit;

function send_security_headers(): void
{
    $csp = "default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; "
         . "font-src 'self'; connect-src 'self'; manifest-src 'self'; worker-src 'self'; "
         . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'";
    if (is_https()) {
        $csp .= '; upgrade-insecure-requests';
        header('Strict-Transport-Security: max-age=31536000');
    }
    header('Content-Security-Policy: ' . $csp);
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
}

function app_key(): string
{
    static $key = null;
    if ($key === null) {
        $raw = base64_decode((string) cfg('app_key', ''), true);
        if ($raw === false || strlen($raw) < 32) {
            throw new RuntimeException('app_key missing or too short in app/config.php');
        }
        $key = substr($raw, 0, 32);
    }
    return $key;
}

function hmac(string $data): string
{
    return hash_hmac('sha256', $data, app_key());
}

/** Pseudonymous IP identifier for rate limiting (never store raw IPs). */
function ip_ident(): string
{
    return hmac('ip|' . client_ip());
}

/* ---------- Public-site CSRF: signed double-submit cookie (no PHP session needed) ---------- */

function csrf_token(): string
{
    static $token = null;
    if ($token !== null) {
        return $token;
    }
    $seed = $_COOKIE['ps_csrf'] ?? '';
    if (!is_string($seed) || !preg_match('/^[a-f0-9]{32}$/', $seed)) {
        $seed = bin2hex(random_bytes(16));
        set_cookie('ps_csrf', $seed, 0, ['samesite' => 'Strict']);
        $_COOKIE['ps_csrf'] = $seed;
    }
    return $token = hmac('csrf|' . $seed);
}

function csrf_valid(mixed $submitted): bool
{
    $seed = $_COOKIE['ps_csrf'] ?? '';
    if (!is_string($submitted) || !is_string($seed) || !preg_match('/^[a-f0-9]{32}$/', $seed)) {
        return false;
    }
    return hash_equals(hmac('csrf|' . $seed), $submitted);
}

/** Same-origin check for state-changing requests (defence in depth on top of CSRF tokens). */
function same_origin_request(): bool
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '' || $origin === 'null') {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        if ($ref === '') {
            return true; // some privacy tools strip both; CSRF token still protects
        }
        $origin = $ref;
    }
    $host = parse_url($origin, PHP_URL_HOST);
    $port = parse_url($origin, PHP_URL_PORT);
    $expected = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $actual = strtolower((string) $host) . ($port ? ':' . $port : '');
    return $actual === $expected;
}

/* ---------- Anti-bot timestamp: form must be at least N seconds old ---------- */

function form_ts(): string
{
    $t = (string) time();
    return $t . '.' . substr(hmac('ts|' . $t), 0, 20);
}

function form_ts_ok(mixed $value, int $minSeconds = 3, int $maxSeconds = 7200): bool
{
    if (!is_string($value) || !preg_match('/^(\d{10})\.([a-f0-9]{20})$/', $value, $m)) {
        return false;
    }
    if (!hash_equals(substr(hmac('ts|' . $m[1]), 0, 20), $m[2])) {
        return false;
    }
    $age = time() - (int) $m[1];
    return $age >= $minSeconds && $age <= $maxSeconds;
}

/* ---------- Encryption of customer personal data at rest ---------- */

function encrypt_str(string $plain): string
{
    $key = app_key();
    if (function_exists('sodium_crypto_secretbox')) {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 's1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
    }
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return 'g1:' . base64_encode($iv . $tag . $ct);
}

function decrypt_str(?string $blob): ?string
{
    if ($blob === null || strlen($blob) < 4) {
        return null;
    }
    $key = app_key();
    $raw = base64_decode(substr($blob, 3), true);
    if ($raw === false) {
        return null;
    }
    if (str_starts_with($blob, 's1:') && function_exists('sodium_crypto_secretbox_open')) {
        $n = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        $out = sodium_crypto_secretbox_open(substr($raw, $n), substr($raw, 0, $n), $key);
        return $out === false ? null : $out;
    }
    if (str_starts_with($blob, 'g1:')) {
        $out = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $out === false ? null : $out;
    }
    return null;
}

/* ---------- Rate limiting (DB backed, works on shared hosting) ---------- */

/**
 * Returns false when $ident already hit $bucket $max times within $windowSec.
 * When $record is true, the current attempt is counted.
 */
function rate_allow(string $bucket, string $ident, int $max, int $windowSec, bool $record = true): bool
{
    $pdo = db();
    $since = date('Y-m-d H:i:s', time() - $windowSec);
    $st = $pdo->prepare('SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND ident = ? AND created_at > ?');
    $st->execute([$bucket, $ident, $since]);
    $count = (int) $st->fetchColumn();
    if ($count >= $max) {
        return false;
    }
    if ($record) {
        rate_record($bucket, $ident);
    }
    return true;
}

function rate_record(string $bucket, string $ident): void
{
    db()->prepare('INSERT INTO rate_limits (bucket, ident, created_at) VALUES (?, ?, ?)')
        ->execute([$bucket, $ident, now_str()]);
    if (random_int(1, 50) === 1) {
        db()->prepare('DELETE FROM rate_limits WHERE created_at < ?')
            ->execute([date('Y-m-d H:i:s', time() - 86400)]);
    }
}
