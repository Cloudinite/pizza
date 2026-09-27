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

$notFound = static function () use ($root) {
    http_response_code(404);
    require $root . '/404.php';
    return true;
};

// private files → 404 (same rules as .htaccess)
if (preg_match('#^/(app|dev|vendor|node_modules)(/|$)#i', $path)
    || (preg_match('#(^|/)\.#', $path) && !str_starts_with($path, '/.well-known/'))
    || preg_match('#\.(sql|md|lock|log|ini|sh|bak|dist|example|sample|swp|old|orig|tmp|env|zip|tar|gz|tgz|7z|rar|yml|yaml|inc|phar|phtml)$#i', $path)
    || preg_match('#(~|\#)$#', $path)
    || preg_match('#^/(assets|uploads)/(.*\.ph(p\d?|tml|ar))?$#i', $path)) {
    return $notFound();
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
// the old /admin/ folder only holds a redirect-to-router .htaccess
if (preg_match('#^/admin(/|$)#', $path)) {
    require $root . '/route.php';
    return true;
}
$file = $root . $path;
if (is_dir($file) && is_file(rtrim($file, '/') . '/index.php')) {
    $file = rtrim($file, '/') . '/index.php';
}
if (is_file($file)) {
    if (str_ends_with($file, '.php')) {
        chdir(dirname($file));
        require $file;
        return true;
    }
    return false; // let the built-in server send static files
}
// anything else → router (secret admin address or 404)
require $root . '/route.php';
return true;
