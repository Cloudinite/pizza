<?php
defined('PS_APP') || exit;

/** Admin app shell: top bar + bottom tab bar, installable as a PWA (scope /admin/). */
function admin_start(string $title, string $tab, array $o = []): void
{
    send_security_headers();
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    $u = admin_current();
    ?>
<!doctype html>
<html lang="sk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> – Pizza Slice Admin</title>
<link rel="manifest" href="/admin/manifest.webmanifest">
<meta name="theme-color" content="#b41116">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="PS Admin">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="format-detection" content="telephone=no">
<link rel="apple-touch-icon" href="/admin/assets/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="192x192" href="/admin/assets/icon-192.png">
<link rel="preload" href="/assets/fonts/pjs-sk.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('/admin/assets/admin.css')) ?>">
<script src="<?= e(asset('/admin/assets/admin.js')) ?>" defer></script>
<?php if ($u): ?><meta name="csrf-token" content="<?= e(admin_csrf()) ?>"><?php endif; ?>
</head>
<body class="adm<?= $u ? '' : ' is-guest' ?>" data-page="<?= e($tab) ?>">
<header class="a-top">
  <div class="a-top-in">
    <img src="/assets/img/logo-104.webp" width="40" height="37" alt="">
    <h1><?= e($title) ?></h1>
    <?php if (!empty($o['top_right'])) { echo $o['top_right']; } ?>
  </div>
</header>
<main class="a-main">
<?php if (!empty($_GET['ok'])): ?>
  <p class="a-flash" role="status"><?= e(admin_flash_text((string) $_GET['ok'])) ?></p>
<?php endif; ?>
<?php
}

function admin_end(): void
{
    $u = admin_current();
    ?>
</main>
<?php if ($u): ?>
<nav class="a-tabs" aria-label="Sekcie administrácie">
  <a href="/admin/" data-tab="orders"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M6 3h12a1 1 0 0 1 1 1v17l-3-2-2 2-2-2-2 2-2-2-3 2V4a1 1 0 0 1 1-1zm3 5h6m-6 4h6m-6 4h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Objednávky</span><b class="a-tab-badge" data-new-badge hidden>0</b></a>
  <a href="/admin/menu.php" data-tab="menu"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 6c5-3 11-3 16 0L12.6 20.4a.7.7 0 0 1-1.2 0z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="10" cy="10" r="1.3" fill="currentColor"/><circle cx="14" cy="12" r="1.3" fill="currentColor"/></svg><span>Menu</span></a>
  <a href="/admin/settings.php" data-tab="settings"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3M4.9 4.9 7 7m10 10 2.1 2.1M4.9 19.1 7 17m10-10 2.1-2.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>Nastavenia</span></a>
  <a href="/" target="_blank" rel="noopener" data-tab="web"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18" fill="none" stroke="currentColor" stroke-width="2"/></svg><span>Web</span></a>
</nav>
<?php endif; ?>
</body>
</html>
<?php
}

function admin_flash_text(string $key): string
{
    return [
        'saved' => 'Uložené.',
        'deleted' => 'Zmazané.',
        'added' => 'Pridané.',
        'password' => 'Heslo bolo zmenené. Ostatné zariadenia boli odhlásené.',
    ][$key] ?? 'Hotovo.';
}

/** Hidden CSRF input for admin forms. */
function admin_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(admin_csrf()) . '">';
}
