<?php
/**
 * Everything that is not a real public page or file ends up here (see .htaccess).
 * It serves the admin app only under its secret address and a normal 404 for anything else.
 */
require __DIR__ . '/app/bootstrap.php';
require PS_APPDIR . '/lib/auth.php';
require PS_APPDIR . '/lib/admin_view.php';
require PS_APPDIR . '/admin/router.php';

admin_route((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
