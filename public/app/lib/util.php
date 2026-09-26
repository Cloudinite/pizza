<?php
defined('PS_APP') || exit;

function cfg(string $key, mixed $default = null): mixed
{
    return $GLOBALS['PS_CONFIG'][$key] ?? $default;
}

function e(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 250 → "2,50 €" (non-breaking space before the euro sign). */
function money(int $cents): string
{
    return number_format($cents / 100, 2, ',', "\u{00A0}") . "\u{00A0}€";
}

/** "2,50" / "2.5" / "2,50 €" → 250; null when not a valid price. */
function parse_money(string $s): ?int
{
    $s = str_replace([' ', "\u{00A0}", '€'], '', trim($s));
    $s = str_replace(',', '.', $s);
    if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $s)) {
        return null;
    }
    return (int) round(((float) $s) * 100);
}

function now_str(): string
{
    return date('Y-m-d H:i:s');
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** Absolute site URL without trailing slash, e.g. https://www.pizzaslice.sk */
function base_url(): string
{
    $configured = rtrim((string) cfg('base_url', ''), '/');
    if ($configured !== '') {
        return $configured;
    }
    $host = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return (is_https() ? 'https' : 'http') . '://' . $host;
}

function abs_url(string $path = '/'): string
{
    return base_url() . $path;
}

/** Cache-busted asset URL: /assets/css/site.css?v=1712345678 */
function asset(string $path): string
{
    $file = PS_PUBLIC . $path;
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return $path . '?v=' . $v;
}

function redirect(string $to, int $code = 303): never
{
    header('Location: ' . $to, true, $code);
    exit;
}

function json_out(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post_str(string $key, int $maxLen = 500): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    $v = trim(str_replace("\0", '', $v));
    // collapse control characters except newlines
    $v = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr($v, 0, $maxLen);
}

function set_cookie(string $name, string $value, int $maxAge, array $opts = []): void
{
    setcookie($name, $value, [
        'expires'  => $maxAge > 0 ? time() + $maxAge : ($maxAge < 0 ? time() - 3600 : 0),
        'path'     => $opts['path'] ?? '/',
        'secure'   => is_https(),
        'httponly' => $opts['httponly'] ?? true,
        'samesite' => $opts['samesite'] ?? 'Lax',
    ]);
}

function not_found(): never
{
    http_response_code(404);
    require PS_PUBLIC . '/404.php';
    exit;
}

/** Slovak plural helper: plural(3, 'kúsok', 'kúsky', 'kúskov'). */
function plural(int $n, string $one, string $few, string $many): string
{
    if ($n === 1) {
        return $one;
    }
    return ($n >= 2 && $n <= 4) ? $few : $many;
}
