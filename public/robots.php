<?php
require __DIR__ . '/app/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=86400');
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /admin/\n";
echo "Disallow: /api/\n";
echo "Disallow: /install/\n";
echo "Disallow: /kosik\n";
echo "Disallow: /objednavka/\n\n";
echo 'Sitemap: ' . abs_url('/sitemap.xml') . "\n";
