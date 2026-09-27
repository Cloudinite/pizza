<?php
defined('PS_APP') || exit;
// Service worker with the secret admin address baked in (scope = that address).
header('Content-Type: text/javascript; charset=utf-8');
header('Cache-Control: no-cache');
header('Service-Worker-Allowed: ' . admin_url());
while (ob_get_level() > 0) {
    ob_end_clean();
}
echo str_replace("'__BASE__'", json_encode(admin_url(), JSON_UNESCAPED_SLASHES), (string) file_get_contents(__DIR__ . '/sw.js'));
