<?php
declare(strict_types=1);
namespace CloakPaste\Config;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function getInstance(): PDO
    {
        if (self::$pdo === null) {
            // DB_* wins; MYSQL* are the names Railway's MySQL plugin provides.
            $pick = fn(string $a, string $b, string $d) => Env::get($a) ?: (Env::get($b) ?: $d);
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $pick('DB_HOST', 'MYSQLHOST', '127.0.0.1'),
                (int)$pick('DB_PORT', 'MYSQLPORT', '3306'),
                $pick('DB_NAME', 'MYSQLDATABASE', 'cloakpaste')
            );
            self::$pdo = new PDO($dsn, $pick('DB_USER', 'MYSQLUSER', ''), Env::get('DB_PASS') ?? (Env::get('MYSQLPASSWORD') ?? ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }
}
