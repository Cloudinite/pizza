<?php
defined('PS_APP') || exit;

/*
 * Front door for everything that is not a real public file or page (see route.php / .htaccess).
 * Serves the admin app only under its secret address; everything else is a plain 404.
 */

const ADMIN_PAGES = [
    ''                     => 'index.php',
    'prihlasenie'          => 'login.php',
    'odhlasenie'           => 'logout.php',
    'menu'                 => 'menu.php',
    'polozka'              => 'item.php',
    'nastavenia'           => 'settings.php',
    'kupony'               => 'coupons.php',
    'zabezpecenie'         => 'security.php',
    'api'                  => 'api.php',
    'sw.js'                => 'sw.php',
    'manifest.webmanifest' => 'manifest.php',
    'offline'              => 'offline.php',
];

function admin_route(string $uriPath): never
{
    $path = rawurldecode($uriPath);
    $parts = explode('/', ltrim($path, '/'), 2);
    $first = strtolower($parts[0]);
    $rest = $parts[1] ?? null;

    if ($first !== '' && hash_equals(admin_base(), $first)) {
        if ($rest === null) {
            redirect(admin_url(), 301);
        }
        admin_dispatch($rest);
    }

    // One-time door for sites upgraded from the old /admin/ address: after logging in there,
    // the owner is shown the new secret address. Once confirmed, /admin/ is a 404 like anything else.
    if ($first === 'admin' && setting('admin_path_ack') !== '1') {
        define('PS_LEGACY_LOGIN', true);
        if (in_array($rest ?? '', ['', 'login.php', 'index.php'], true)) {
            header('Referrer-Policy: same-origin');
            require __DIR__ . '/login.php';
            exit;
        }
        if (preg_match('#^assets/(admin\.(css|js)|icon-192\.png|apple-touch-icon\.png)$#', (string) $rest, $m)) {
            admin_send_asset($m[1], pathinfo($m[1], PATHINFO_EXTENSION));
        }
    }

    not_found();
}

function admin_dispatch(string $rest): never
{
    header('Referrer-Policy: same-origin');
    header('X-Robots-Tag: noindex, nofollow');
    if (isset(ADMIN_PAGES[$rest])) {
        require __DIR__ . '/' . ADMIN_PAGES[$rest];
        exit;
    }
    if (preg_match('#^assets/([a-z0-9-]+)\.(css|js|png|webp|svg)$#', $rest, $m)) {
        admin_send_asset($m[1] . '.' . $m[2], $m[2]);
    }
    not_found();
}

function admin_send_asset(string $file, string $ext): never
{
    $full = __DIR__ . '/assets/' . $file;
    if (!is_file($full)) {
        not_found();
    }
    $types = [
        'css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8',
        'png' => 'image/png', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
    ];
    $etag = '"' . dechex(filemtime($full)) . '-' . dechex(filesize($full)) . '"';
    header('Content-Type: ' . $types[$ext]);
    header('X-Content-Type-Options: nosniff');
    header('ETag: ' . $etag);
    header('Cache-Control: ' . (isset($_GET['v']) ? 'private, max-age=31536000, immutable' : 'private, no-cache'));
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    readfile($full);
    exit;
}
