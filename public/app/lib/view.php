<?php
defined('PS_APP') || exit;

/**
 * Prints <head>, announcement bar and site header.
 * Options: title, description, path (canonical), nav, noindex, jsonld (list), preload (list of [href, as, type?]).
 */
function page_start(array $o = []): void
{
    send_security_headers();
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache, private');

    $s = settings();
    $title = $o['title'] ?? $s['site_name'];
    $desc = $o['description'] ?? 'Pizza na kúsky v Pezinku. Vždy čerstvá, každý deň. Objednajte online a ' . fulfilment_phrase(true) . '.';
    $canonical = abs_url($o['path'] ?? '/');
    $nav = $o['nav'] ?? '';
    $count = cart_count_quick();
    $jsonld = $o['jsonld'] ?? [];
    ?>
<!doctype html>
<html lang="sk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<?php if (!empty($o['noindex'])): ?>
<meta name="robots" content="noindex, nofollow">
<?php else: ?>
<link rel="canonical" href="<?= e($canonical) ?>">
<?php endif; ?>
<meta name="theme-color" content="#b41116">
<meta name="format-detection" content="telephone=no">
<link rel="preload" href="/assets/fonts/pjs-sk.woff2" as="font" type="font/woff2" crossorigin>
<?php foreach ($o['preload'] ?? [] as $p): ?>
<link rel="preload" href="<?= e($p[0]) ?>" as="<?= e($p[1]) ?>"<?= isset($p[2]) ? ' imagesrcset="' . e($p[2]) . '" imagesizes="' . e($p[3] ?? '100vw') . '"' : '' ?> fetchpriority="high">
<?php endforeach; ?>
<link rel="stylesheet" href="<?= e(asset('/assets/css/site.css')) ?>">
<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="icon" type="image/png" sizes="48x48" href="/assets/img/favicon-48.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta property="og:type" content="website">
<meta property="og:locale" content="sk_SK">
<meta property="og:site_name" content="<?= e($s['site_name']) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e(abs_url('/assets/img/og.jpg')) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<script src="<?= e(asset('/assets/js/site.js')) ?>" defer></script>
<?php foreach ($jsonld as $ld): ?>
<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endforeach; ?>
</head>
<body class="<?= e($o['body_class'] ?? '') ?>">
<a class="skip" href="#main">Preskočiť na obsah</a>
<?php if (trim($s['announcement']) !== ''): ?>
<div class="announce" role="status"><div class="wrap"><?= e($s['announcement']) ?></div></div>
<?php endif; ?>
<?php if (empty($o['no_track_bar'])) { tracking_bar(); } ?>
<header class="hdr">
  <div class="wrap hdr-in">
    <a class="brand" href="/" aria-label="<?= e($s['site_name']) ?> – úvod">
      <img src="/assets/img/logo-104.webp" srcset="/assets/img/logo-104.webp 104w, /assets/img/logo-208.webp 208w" sizes="52px" width="52" height="48" alt="">
      <span class="brand-t"><b>Pizza Slice</b><small>Pezinok</small></span>
    </a>
    <nav class="nav" aria-label="Hlavná navigácia">
      <a href="/menu"<?= $nav === 'menu' ? ' aria-current="page"' : '' ?>>Menu</a>
      <a href="/#kontakt">Kontakt</a>
    </nav>
    <a class="cartbtn<?= $nav === 'cart' ? ' is-current' : '' ?>" href="/kosik" data-cart-link aria-label="Košík – <?= $count ?> ks">
      <svg class="ico" aria-hidden="true"><use href="#i-bag"/></svg>
      <span class="cartbtn-t">Košík</span>
      <span class="badge" data-cart-count<?= $count ? '' : ' hidden' ?>><?= $count ?></span>
    </a>
  </div>
</header>
<main id="main">
<?php
}

function page_end(): void
{
    $s = settings();
    $state = open_state();
    ?>
</main>
<?php brand_band(); ?>
<footer class="ftr">
  <div class="wrap ftr-grid">
    <div class="ftr-brand">
      <img src="/assets/img/logo-208.webp" width="104" height="95" alt="Pizza Slice – Always fresh everyday" loading="lazy">
      <p><?= e($s['tagline']) ?></p>
      <?php social_links(); ?>
    </div>
    <div>
      <h2>Otváracie hodiny</h2>
      <?php hours_table(); ?>
      <p class="status-inline"><span class="dot<?= $state['open'] ? ' is-open' : '' ?>" aria-hidden="true"></span><?= e($state['text']) ?></p>
    </div>
    <div>
      <h2>Kontakt</h2>
      <address>
        <?= e($s['site_name']) ?><br>
        <?php if (address_line() !== ''): ?><?= e(address_line()) ?><br><?php endif; ?>
        <?php if ($s['phone'] !== ''): ?><a href="<?= e(phone_href($s['phone'])) ?>"><?= e($s['phone']) ?></a><br><?php endif; ?>
        <?php if ($s['email'] !== ''): ?><a href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a><?php endif; ?>
      </address>
    </div>
    <div>
      <h2>Informácie</h2>
      <ul class="ftr-links">
        <li><a href="/menu">Menu a objednávka</a></li>
        <li><a href="/ochrana-osobnych-udajov">Ochrana osobných údajov</a></li>
        <li><a href="/cookies">Zásady používania cookies</a></li>
      </ul>
    </div>
  </div>
  <div class="wrap ftr-bottom">
    <p>© <?= date('Y') ?> <?= e($s['company_name'] !== '' ? $s['company_name'] : $s['site_name']) ?><?= $s['company_ico'] !== '' ? ' · IČO ' . e($s['company_ico']) : '' ?></p>
  </div>
</footer>
<?php svg_sprite(); ?>
</body>
</html>
<?php
}

/** "Vždy svieže každý deň · Always fresh everyday" strip, scrolling left→right (decorative). */
function brand_band(string $extraClass = ''): void
{
    $item = static fn (string $t) => '<span class="band-item">' . $t . '<svg class="band-ico" aria-hidden="true"><use href="#i-slice"/></svg></span>';
    $group = '';
    for ($i = 0; $i < 5; $i++) {
        $group .= $item('VŽDY SVIEŽE KAŽDÝ DEŇ') . $item('ALWAYS FRESH EVERYDAY');
    }
    // two identical halves → seamless loop
    echo '<div class="band ' . e($extraClass) . '" aria-hidden="true"><div class="band-track">'
        . '<div class="band-group">' . $group . '</div><div class="band-group">' . $group . '</div></div></div>';
}

/** Slim bar linking to the visitor's own running order (from the ps_track cookie). */
function tracking_bar(): void
{
    $raw = $_COOKIE['ps_track'] ?? '';
    if (!is_string($raw) || !preg_match('/^([A-Z0-9]{6})\.([a-f0-9]{32})$/', $raw, $m)) {
        return;
    }
    $o = order_find_public($m[1], $m[2]);
    if (!$o || !empty($o['expired']) || !in_array($o['status'], ORDER_ACTIVE, true)) {
        return;
    }
    $label = order_steps($o['fulfillment'])[$o['status']] ?? ORDER_STATUSES[$o['status']];
    echo '<div class="trackbar"><div class="wrap trackbar-in"><span>Tvoja objednávka č. <b>' . (int) $o['daily_no'] . '</b> · ' . e($label) . '</span>'
        . '<a href="/objednavka/' . e($m[1]) . '?k=' . e($m[2]) . '">Sledovať stav →</a></div></div>';
}

function social_links(string $class = 'social'): void
{
    $fb = setting('facebook_url');
    $ig = setting('instagram_url');
    if ($fb === '' && $ig === '') {
        return;
    }
    echo '<ul class="' . e($class) . '">';
    if ($fb !== '') {
        echo '<li><a href="' . e($fb) . '" rel="noopener" target="_blank"><svg class="ico" aria-hidden="true"><use href="#i-fb"/></svg><span>Facebook</span></a></li>';
    }
    if ($ig !== '') {
        echo '<li><a href="' . e($ig) . '" rel="noopener" target="_blank"><svg class="ico" aria-hidden="true"><use href="#i-ig"/></svg><span>Instagram</span></a></li>';
    }
    echo '</ul>';
}

function hours_table(): void
{
    echo '<table class="hours"><caption class="sr">Otváracie hodiny</caption><tbody>';
    foreach (hours_rows() as [$days, $time]) {
        echo '<tr><th scope="row">' . e($days) . '</th><td>' . e($time) . '</td></tr>';
    }
    echo '</tbody></table>';
}

function item_icon(string $icon): string
{
    $id = in_array($icon, ['slice', 'drink', 'star'], true) ? $icon : 'slice';
    return '<svg class="thumb-ico" aria-hidden="true"><use href="#i-' . $id . '"/></svg>';
}

function schema_restaurant(): array
{
    $s = settings();
    $ld = [
        '@context' => 'https://schema.org',
        '@type' => 'Restaurant',
        '@id' => abs_url('/#restaurant'),
        'name' => $s['site_name'],
        'url' => abs_url('/'),
        'logo' => abs_url('/assets/img/logo-512.png'),
        'image' => abs_url('/assets/img/og.jpg'),
        'description' => $s['tagline'],
        'servesCuisine' => ['Pizza', 'Talianska'],
        'priceRange' => '€',
        'hasMenu' => abs_url('/menu'),
        'acceptsReservations' => false,
        'address' => array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $s['street'],
            'postalCode' => $s['zip'],
            'addressLocality' => $s['city'],
            'addressCountry' => 'SK',
        ]),
        'openingHoursSpecification' => opening_hours_schema(),
        'sameAs' => array_values(array_filter([$s['facebook_url'], $s['instagram_url']])),
    ];
    if ($s['phone'] !== '') {
        $ld['telephone'] = $s['phone'];
    }
    if ($s['email'] !== '') {
        $ld['email'] = $s['email'];
    }
    return $ld;
}

function svg_sprite(): void
{
    ?>
<svg class="sprite" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
  <symbol id="i-slice" viewBox="0 0 64 64"><path d="M8 14c15-8 33-8 48 0L33.5 58a2 2 0 0 1-3 0z" fill="#f7c548"/><path d="M8 14c15-8 33-8 48 0l-2.5 5c-13.5-7-29.5-7-43 0z" fill="#d9892b"/><circle cx="24" cy="26" r="4.5" fill="#c8262b"/><circle cx="39" cy="28" r="4.5" fill="#c8262b"/><circle cx="31" cy="41" r="3.6" fill="#c8262b"/><circle cx="31" cy="23" r="1.6" fill="#2e7d32"/><circle cx="26" cy="35" r="1.4" fill="#2e7d32"/><circle cx="37" cy="36" r="1.4" fill="#1b1b1f"/></symbol>
  <symbol id="i-drink" viewBox="0 0 64 64"><path d="M33 15 39 3" stroke="#1b1b1f" stroke-width="3" stroke-linecap="round" fill="none"/><path d="M17 17h30l-3.6 40a4 4 0 0 1-4 3.6H24.6a4 4 0 0 1-4-3.6z" fill="#b41116"/><path d="M19.3 28h25.4l-.7 8H20z" fill="#fff8ee"/><rect x="14" y="13" width="36" height="6" rx="2" fill="#8e0c10"/></symbol>
  <symbol id="i-star" viewBox="0 0 64 64"><path d="m32 5 8.2 17.6 19.3 2.3-14.3 13.2 3.8 19L32 47.6 15 57.1l3.8-19L4.5 24.9l19.3-2.3z" fill="#f2a900"/><path d="m32 16 5.3 11.3 12.4 1.5-9.2 8.5 2.5 12.2L32 43.4l-11 6.1 2.5-12.2-9.2-8.5 12.4-1.5z" fill="#ffd45c"/></symbol>
  <symbol id="i-bag" viewBox="0 0 24 24"><path d="M6 7h12l1 13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1z M9 7V6a3 3 0 0 1 6 0v1" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></symbol>
  <symbol id="i-fb" viewBox="0 0 24 24"><path fill="currentColor" d="M13.4 22v-8.1h2.8l.4-3.3h-3.2V8.5c0-.9.3-1.6 1.6-1.6h1.7V4c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.4H7.2v3.3H10V22z"/></symbol>
  <symbol id="i-ig" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.4" cy="6.6" r="1.3" fill="currentColor"/></symbol>
  <symbol id="i-phone" viewBox="0 0 24 24"><path fill="currentColor" d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25c1.1.37 2.3.57 3.6.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z"/></symbol>
  <symbol id="i-pin" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></symbol>
  <symbol id="i-ticket" viewBox="0 0 24 24"><path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M14 6v2m0 3v2m0 3v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
  <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7v5l3 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
</svg>
<?php
}
