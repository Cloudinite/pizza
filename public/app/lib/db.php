<?php
defined('PS_APP') || exit;

/** Bump when a migration is added below; existing databases upgrade themselves on the next request. */
const PS_SCHEMA_VERSION = 2;

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = db_connect(
            (string) cfg('db_host', 'localhost'),
            (int) cfg('db_port', 3306),
            (string) cfg('db_name', ''),
            (string) cfg('db_user', ''),
            (string) cfg('db_pass', '')
        );
    }
    return $pdo;
}

function db_connect(string $host, int $port, string $name, string $user, string $pass): PDO
{
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ]);
}

/** Run a .sql file statement by statement (used by the installer). */
function db_run_sql_file(PDO $pdo, string $file): void
{
    $sql = (string) file_get_contents($file);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $stmt) {
        $stmt = trim($stmt);
        if ($stmt !== '') {
            $pdo->exec($stmt);
        }
    }
}

/**
 * Upgrades an existing database to PS_SCHEMA_VERSION. Idempotent and safe to run twice
 * (e.g. two requests at once), so a live site updates itself when new files are uploaded.
 */
function db_migrate(PDO $pdo, int $from): void
{
    if ($from < 2) {
        db_run_sql_file($pdo, PS_APPDIR . '/sql/002_coupons.sql');
        if (!$pdo->query("SHOW COLUMNS FROM orders LIKE 'discount_cents'")->fetch()) {
            try {
                $pdo->exec('ALTER TABLE orders
                    ADD COLUMN coupon_code VARCHAR(32) NULL AFTER delivery_cents,
                    ADD COLUMN discount_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER coupon_code');
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? 0) !== 1060) { // 1060 = column already added by a parallel request
                    throw $e;
                }
            }
        }
    }
    $pdo->prepare("INSERT INTO settings (k, v) VALUES ('schema_version', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)")
        ->execute([(string) PS_SCHEMA_VERSION]);
}
