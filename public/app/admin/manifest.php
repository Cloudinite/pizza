<?php
defined('PS_APP') || exit;
// Web app manifest for the installable admin app (addresses depend on the secret admin path).
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache');
$base = admin_url();
echo json_encode([
    'name' => 'Pizza Slice – Admin',
    'short_name' => 'PS Admin',
    'description' => 'Objednávky, menu a nastavenia prevádzky Pizza Slice Pezinok.',
    'id' => $base,
    'start_url' => $base,
    'scope' => $base,
    'display' => 'standalone',
    'orientation' => 'any',
    'background_color' => '#f4f1ec',
    'theme_color' => '#b41116',
    'lang' => 'sk',
    'categories' => ['business', 'food'],
    'icons' => [
        ['src' => admin_url('assets/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => admin_url('assets/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => admin_url('assets/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    'shortcuts' => [
        ['name' => 'Objednávky', 'url' => $base],
        ['name' => 'Menu', 'url' => admin_url('menu')],
        ['name' => 'Nastavenia', 'url' => admin_url('nastavenia')],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
