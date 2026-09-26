<?php
require __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$pages = [
    ['/', 'daily', '1.0', __DIR__ . '/index.php'],
    ['/menu', 'daily', '0.9', __DIR__ . '/menu.php'],
    ['/ochrana-osobnych-udajov', 'yearly', '0.2', __DIR__ . '/ochrana-osobnych-udajov.php'],
    ['/cookies', 'yearly', '0.2', __DIR__ . '/cookies.php'],
];
$menuUpdated = db()->query('SELECT MAX(updated_at) FROM menu_items')->fetchColumn();
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
foreach ($pages as [$path, $freq, $prio, $file]) {
    $mod = filemtime($file);
    if ($path === '/menu' && $menuUpdated) {
        $mod = max($mod, strtotime((string) $menuUpdated));
    }
    echo '  <url><loc>', e(abs_url($path)), '</loc><lastmod>', date('Y-m-d', $mod), '</lastmod>',
        '<changefreq>', $freq, '</changefreq><priority>', $prio, '</priority></url>', "\n";
}
echo '</urlset>', "\n";
