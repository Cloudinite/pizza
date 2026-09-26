<?php
defined('PS_APP') || exit;

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
