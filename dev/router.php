<?php
/**
 * Local development router – emulates public/.htaccess for PHP's built-in server:
 *   php -S localhost:8080 -t public dev/router.php
 * Not needed (and not uploaded) on WebSupport, where Apache uses .htaccess.
 */
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__) . '/public';
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

if (preg_match('#^/app(/|$)#', $path) || preg_match('#/\.#', $path) || preg_match('#\.(sql|md|lock|log)$#', $path)) {
    http_response_code(404);
    require $root . '/404.php';
    return true;
}
$routes = [
    '#^/menu/?$#' => '/menu.php',
    '#^/kosik/?$#' => '/kosik.php',
    '#^/ochrana-osobnych-udajov/?$#' => '/ochrana-osobnych-udajov.php',
    '#^/cookies/?$#' => '/cookies.php',
    '#^/sitemap\.xml$#' => '/sitemap.php',
    '#^/robots\.txt$#' => '/robots.php',
];
foreach ($routes as $re => $script) {
    if (preg_match($re, $path)) {
        $_SERVER['SCRIPT_NAME'] = $script;
        require $root . $script;
        return true;
    }
}
if (preg_match('#^/objednavka/([A-Za-z0-9]{6})/?$#', $path, $m)) {
    $_GET['code'] = $m[1];
    require $root . '/objednavka.php';
    return true;
}
$file = $root . $path;
if (is_dir($file)) {
    $file = rtrim($file, '/') . '/index.php';
}
if (is_file($file)) {
    if (str_ends_with($file, '.php')) {
        chdir(dirname($file));
        require $file;
        return true;
    }
    if (str_ends_with($file, '.webmanifest')) {
        header('Content-Type: application/manifest+json');
        readfile($file);
        return true;
    }
    return false; // let the built-in server send static files
}
http_response_code(404);
require $root . '/404.php';
return true;
