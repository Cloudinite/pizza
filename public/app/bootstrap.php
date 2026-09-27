<?php
/**
 * Pizza Slice Pezinok – shared bootstrap. Every PHP entry point requires this file.
 */
declare(strict_types=1);

define('PS_APP', true);
define('PS_PUBLIC', dirname(__DIR__));
define('PS_APPDIR', __DIR__);

// Buffer output so headers/cookies set later in a request are never lost.
ob_start();
header_remove('X-Powered-By'); // do not advertise the PHP version

date_default_timezone_set('Europe/Bratislava');
mb_internal_encoding('UTF-8');

$psConfigFile = __DIR__ . '/config.php';
$GLOBALS['PS_CONFIG'] = is_file($psConfigFile) ? (require $psConfigFile) : [];
if (!is_array($GLOBALS['PS_CONFIG'])) {
    $GLOBALS['PS_CONFIG'] = [];
}

ini_set('display_errors', !empty($GLOBALS['PS_CONFIG']['debug']) ? '1' : '0');
error_reporting(E_ALL);

require __DIR__ . '/lib/util.php';
require __DIR__ . '/lib/security.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/settings.php';
require __DIR__ . '/lib/hours.php';
require __DIR__ . '/lib/menu.php';
require __DIR__ . '/lib/orders.php';
require __DIR__ . '/lib/coupons.php';
require __DIR__ . '/lib/admin_path.php';
require __DIR__ . '/lib/totp.php';
require __DIR__ . '/lib/view.php';

set_exception_handler(static function (Throwable $e): void {
    error_log('[pizzaslice] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }
    if (cfg('debug')) {
        echo '<pre>' . e((string) $e) . '</pre>';
        return;
    }
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Chyba</title><body>'
        . '<h1>Niečo sa pokazilo</h1><p>Skúste to prosím o chvíľu znova. Objednávku môžete urobiť aj telefonicky.</p>'
        . '<p><a href="/">Späť na úvod</a></p>';
});

// Not installed yet → send visitors to the installer (the installer itself defines PS_INSTALLER).
if (!is_file($psConfigFile) && !defined('PS_INSTALLER')) {
    header('Location: /install/', true, 302);
    exit;
}

// New version uploaded over an older database → upgrade it once, automatically.
if (is_file($psConfigFile) && !defined('PS_INSTALLER') && (int) setting('schema_version') < PS_SCHEMA_VERSION) {
    db_migrate(db(), (int) setting('schema_version'));
    settings(true);
}
