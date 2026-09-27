<?php
defined('PS_APP') || exit;

/** Admin app shell: top bar + bottom tab bar, installable as a PWA (scope = the secret admin address). */
function admin_start(string $title, string $tab, array $o = []): void
{
    send_security_headers();
    header('Referrer-Policy: same-origin'); // links to other sites (maps, Instagram …) never see the secret address
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    $u = admin_current();
    $theme = admin_theme();
    $legacy = defined('PS_LEGACY_LOGIN'); // old /admin/ door: never print the secret address here
    ?>
<!doctype html>
<html lang="sk"<?= $theme === 'auto' ? '' : ' data-theme="' . $theme . '"' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> – Pizza Slice Admin</title>
<?php if (!$legacy): ?><link rel="manifest" href="<?= e(admin_url('manifest.webmanifest')) ?>"><?php endif; ?>
<?php if ($theme === 'auto'): ?>
<meta name="theme-color" content="#b41116" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#1b1a1d" media="(prefers-color-scheme: dark)">
<?php else: ?>
<meta name="theme-color" content="<?= $theme === 'dark' ? '#1b1a1d' : '#b41116' ?>">
<?php endif; ?>
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="PS Admin">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="format-detection" content="telephone=no">
<link rel="apple-touch-icon" href="<?= e(admin_asset_href('apple-touch-icon.png')) ?>">
<link rel="icon" type="image/png" sizes="192x192" href="<?= e(admin_asset_href('icon-192.png')) ?>">
<link rel="preload" href="/assets/fonts/pjs-sk.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(admin_asset_href('admin.css')) ?>">
<script src="<?= e(admin_asset_href('admin.js')) ?>" defer></script>
<?php if (!$legacy): ?><meta name="admin-base" content="<?= e(admin_url()) ?>"><?php endif; ?>
<?php if ($u): ?><meta name="csrf-token" content="<?= e(admin_csrf()) ?>"><?php endif; ?>
</head>
<body class="adm<?= $u ? '' : ' is-guest' ?>" data-page="<?= e($tab) ?>">
<header class="a-top">
  <div class="a-top-in">
    <img src="/assets/img/logo-104.webp" width="40" height="37" alt="">
    <h1><?= e($title) ?></h1>
    <?php if (!empty($o['top_right'])) { echo $o['top_right']; } ?>
    <button type="button" class="a-theme-btn" data-theme-btn data-mode="<?= e($theme) ?>" aria-label="Vzhľad: <?= e(ADMIN_THEMES[$theme]) ?> (ťuknutím zmeníte)" title="Svetlý / tmavý režim">
      <svg class="i-light" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      <svg class="i-dark" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
      <svg class="i-auto" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 3.5a8.5 8.5 0 0 1 0 17z" fill="currentColor"/></svg>
    </button>
  </div>
</header>
<main class="a-main">
<?php
}

function admin_end(): void
{
    $u = admin_current();
    ?>
</main>
<?php if (!empty($_GET['ok'])): ?>
<div class="a-toast is-ok is-on" role="status" data-toast data-flash><?= e(admin_flash_text((string) $_GET['ok'])) ?></div>
<?php else: ?>
<div class="a-toast" role="status" data-toast></div>
<?php endif; ?>
<?php if ($u): ?>
<nav class="a-tabs" aria-label="Sekcie administrácie">
  <a href="<?= e(admin_url()) ?>" data-tab="orders"><i class="a-tab-pill" aria-hidden="true"></i><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M6 3h12a1 1 0 0 1 1 1v17l-3-2-2 2-2-2-2 2-2-2-3 2V4a1 1 0 0 1 1-1zm3 5h6m-6 4h6m-6 4h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Objednávky</span><b class="a-tab-badge" data-new-badge hidden>0</b></a>
  <a href="<?= e(admin_url('menu')) ?>" data-tab="menu"><i class="a-tab-pill" aria-hidden="true"></i><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 6c5-3 11-3 16 0L12.6 20.4a.7.7 0 0 1-1.2 0z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="10" cy="10" r="1.3" fill="currentColor"/><circle cx="14" cy="12" r="1.3" fill="currentColor"/></svg><span>Menu</span></a>
  <a href="<?= e(admin_url('nastavenia')) ?>" data-tab="settings"><i class="a-tab-pill" aria-hidden="true"></i><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3M4.9 4.9 7 7m10 10 2.1 2.1M4.9 19.1 7 17m10-10 2.1-2.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>Nastavenia</span></a>
  <a href="/" target="_blank" rel="noopener" data-tab="web"><i class="a-tab-pill" aria-hidden="true"></i><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18" fill="none" stroke="currentColor" stroke-width="2"/></svg><span>Web</span></a>
</nav>
<?php endif; ?>
</body>
</html>
<?php
}

/** Admin asset URL; on the old /admin/ door the files come from there, so the secret address stays hidden. */
function admin_asset_href(string $file): string
{
    if (defined('PS_LEGACY_LOGIN')) {
        $path = PS_APPDIR . '/admin/assets/' . $file;
        return '/admin/assets/' . $file . '?v=' . (is_file($path) ? filemtime($path) : '1');
    }
    return admin_asset_url($file);
}

const ADMIN_THEMES = ['auto' => 'podľa telefónu', 'light' => 'svetlý', 'dark' => 'tmavý'];

/** light / dark / auto (follow the phone) – remembered in a cookie so the server renders the right colours. */
function admin_theme(): string
{
    $t = $_COOKIE['ps_theme'] ?? '';
    return is_string($t) && isset(ADMIN_THEMES[$t]) ? $t : 'auto';
}

function admin_flash_text(string $key): string
{
    return [
        'saved' => 'Uložené.',
        'deleted' => 'Zmazané.',
        'added' => 'Pridané.',
        'password' => 'Heslo bolo zmenené. Ostatné zariadenia boli odhlásené.',
        'moved' => 'Administrácia je na novej adrese – uložte si ju.',
    ][$key] ?? 'Hotovo.';
}

/** Hidden CSRF input for admin forms. */
function admin_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(admin_csrf()) . '">';
}
