<?php

declare(strict_types=1);

namespace MediaPitch\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static array $config = [];
    private static ?PDO $connection = null;

    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            // Long-running AI/research/API requests can outlive a shared-hosting
            // MySQL idle connection. Before handing the cached PDO back, make
            // sure it is still alive. Never probe an active transaction.
            if (self::$connection->inTransaction()) {
                return self::$connection;
            }

            try {
                self::$connection->query('SELECT 1');
                return self::$connection;
            } catch (PDOException $e) {
                if (!self::isLostConnection($e)) {
                    throw $e;
                }
                self::$connection = null;
            }
        }

        return self::connect();
    }

    /** Force the next call to connection() to create a fresh PDO handle. */
    public static function reconnect(): PDO
    {
        self::$connection = null;
        return self::connect();
    }

    private static function connect(): PDO
    {
        if (self::$config === []) {
            throw new RuntimeException('Database is not configured.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            self::$config['host'],
            self::$config['port'],
            self::$config['database'],
            self::$config['charset']
        );

        try {
            self::$connection = new PDO($dsn, self::$config['username'], self::$config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 10,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Unable to connect to the database.', 0, $e);
        }

        return self::$connection;
    }

    public static function isLostConnection(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'server has gone away')
            || str_contains($message, 'lost connection')
            || str_contains($message, 'error while sending query packet')
            || str_contains($message, 'connection was killed')
            || str_contains($message, 'connection is closed')
            || (string)$e->getCode() === '2006'
            || (string)$e->getCode() === '2013';
    }
}
