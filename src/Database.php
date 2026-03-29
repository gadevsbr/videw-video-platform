<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $connection = null;

    private static ?string $lastError = null;

    public static function connection(): ?PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = config('db', []);

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'] ?? '127.0.0.1',
                (int) ($config['port'] ?? 3306),
                $config['database'] ?? '',
                $config['charset'] ?? 'utf8mb4'
            );

            self::$connection = new PDO(
                $dsn,
                $config['username'] ?? 'root',
                $config['password'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            self::$lastError = null;

            return self::$connection;
        } catch (PDOException $exception) {
            self::$lastError = $exception->getMessage();
            return null;
        }
    }

    public static function lastError(): ?string
    {
        return self::$lastError;
    }
}
