<?php
defined('PS_APP') || exit;

/*
 * Time-based one-time passwords (RFC 6238, 6 digits, 30 s) – the codes shown by Google
 * Authenticator, Microsoft Authenticator, Authy, 1Password, Apple Passwords …
 */

function base32_encode(string $bin): string
{
    $abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $ch) {
        $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $abc[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function base32_decode(string $b32): string
{
    $abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32) ?? '');
    $bits = '';
    foreach (str_split($b32) as $ch) {
        $bits .= str_pad(decbin((int) strpos($abc, $ch)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function totp_secret_new(): string
{
    return base32_encode(random_bytes(20)); // 160-bit secret, 32 base32 characters
}

function totp_code(string $secret, int $step): string
{
    $h = hash_hmac('sha1', pack('J', $step), $secret, true);
    $o = ord($h[19]) & 0x0f;
    $v = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string) ($v % 1000000), 6, '0', STR_PAD_LEFT);
}

/**
 * Accepts the current code and one step either side (clock drift). Never accepts a step at or
 * before $lastStep, so an intercepted code cannot be reused.
 * @return int|null the matched time-step
 */
function totp_verify(string $secret, string $code, ?int $lastStep = null): ?int
{
    if ($secret === '' || !preg_match('/^\d{6}$/', $code)) {
        return null;
    }
    $now = intdiv(time(), 30);
    foreach ([0, -1, 1] as $d) {
        $step = $now + $d;
        if ($lastStep !== null && $step <= $lastStep) {
            continue;
        }
        if (hash_equals(totp_code($secret, $step), $code)) {
            return $step;
        }
    }
    return null;
}

function totp_uri(string $secretB32, string $account): string
{
    $issuer = 'Pizza Slice';
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
        . '?secret=' . $secretB32 . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
}
