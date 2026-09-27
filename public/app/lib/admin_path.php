<?php
defined('PS_APP') || exit;

/*
 * The admin app has no fixed, guessable address. It lives at a secret first path segment,
 * e.g. https://example.sk/kuchyna-7k2m9x4q1d/ . Every other unknown address answers with the
 * normal "page not found", so nobody can tell that an admin exists at all.
 *
 * Where the secret comes from (first match wins):
 *   1. 'admin_path' in app/config.php  (lets you recover access if you forget it)
 *   2. the admin_path setting in the database (changeable in the app: Zabezpečenie)
 *   3. generated at random on first use and saved as the setting
 */

/** Public addresses and folders that can never be used as the admin address. */
const ADMIN_RESERVED = [
    'admin', 'app', 'api', 'assets', 'uploads', 'install', 'menu', 'kosik', 'objednavka', 'cookies',
    'ochrana-osobnych-udajov', 'sitemap.xml', 'robots.txt', 'route.php', 'index.php', '404.php',
    'favicon.ico', 'apple-touch-icon.png', 'wp-admin', 'wp-login.php', 'administrator', 'login',
];

function admin_slug_ok(string $slug): bool
{
    return (bool) preg_match('/^[a-z0-9][a-z0-9-]{7,47}$/', $slug) && !in_array($slug, ADMIN_RESERVED, true);
}

/** Random, unguessable address such as "kuchyna-7k2m9x4q1d". */
function admin_slug_random(): string
{
    $words = ['kuchyna', 'prevadzka', 'pult', 'pec', 'objednavky'];
    $abc = 'abcdefghjkmnpqrstuvwxyz23456789';
    $tail = '';
    for ($i = 0; $i < 10; $i++) {
        $tail .= $abc[random_int(0, strlen($abc) - 1)];
    }
    return $words[random_int(0, count($words) - 1)] . '-' . $tail;
}

function admin_base(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $fromConfig = strtolower((string) cfg('admin_path', ''));
    if (admin_slug_ok($fromConfig)) {
        return $base = $fromConfig;
    }
    $fromDb = setting('admin_path');
    if (admin_slug_ok($fromDb)) {
        return $base = $fromDb;
    }
    $new = admin_slug_random();
    save_settings(['admin_path' => $new]);
    return $base = $new;
}

/** "/kuchyna-7k2m9x4q1d/" + page, e.g. admin_url('menu') → "/kuchyna-…/menu" */
function admin_url(string $page = ''): string
{
    return '/' . admin_base() . '/' . $page;
}

function admin_full_url(): string
{
    return abs_url(admin_url());
}

/** Versioned URL of a file in app/admin/assets (served through the router). */
function admin_asset_url(string $file): string
{
    $path = PS_APPDIR . '/admin/assets/' . $file;
    return admin_url('assets/' . $file) . '?v=' . (is_file($path) ? filemtime($path) : '1');
}

/** Admin address is set in config.php → it cannot be changed from the app. */
function admin_path_locked(): bool
{
    return admin_slug_ok(strtolower((string) cfg('admin_path', '')));
}
