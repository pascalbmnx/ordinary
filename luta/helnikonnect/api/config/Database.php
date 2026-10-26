<?php
/**
 * Database connection (PDO singleton).
 *
 * NOTE: On this environment (PHP 8.0.30 + mysqlnd + MariaDB) the PDO driver
 * mis-handles a `USE <dbname>` statement whenever the database name is built
 * at runtime (variable/constant/concatenation) — it fails with
 * "1049 Unknown database". Only a pure inline string literal inside the
 * exec() call reliably selects the schema. The database name here ("db.name"
 * in config.php) must therefore be duplicated in the literal on line ~40.
 * If you change "db.name" in config.php, update that literal to match.
 */
final class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    public static function init(): void
    {
        static $loaded = false;
        if (!$loaded) {
            self::$config = require __DIR__ . '/config.php';
            $loaded = true;
        }
    }

    public static function connection(): PDO
    {
        self::init();
        if (self::$instance === null) {
            $db = self::$config['db'];
            $dsn = sprintf(
                'mysql:host=%s;port=%s;charset=%s',
                $db['host'],
                $db['port'],
                $db['charset']
            );
            self::$instance = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            // Select the working database. MUST be a literal (see note above).
            self::$instance->exec("USE helpkonnect");
        }
        return self::$instance;
    }

    public static function config(string $key = null)
    {
        self::init();
        return $key === null ? self::$config : (self::$config[$key] ?? null);
    }

    public static function logError(string $message): void
    {
        $logDir = dirname(__DIR__, 2) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        @file_put_contents(
            $logDir . '/error.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
            FILE_APPEND
        );
    }
}
