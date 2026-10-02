<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * PDO wrapper. Uses SQLite now; the schema sticks to plain types so it can
 * move to MySQL later by changing the DSN and the ID column definition.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $path = self::path();
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec('PRAGMA foreign_keys = ON');

            self::$pdo = $pdo;
            Migrator::run($pdo);
        }

        return self::$pdo;
    }

    public static function path(): string
    {
        $path = Env::get('DB_PATH', 'storage/app.sqlite');
        return str_starts_with($path, '/') ? $path : BASE_PATH . '/' . $path;
    }

    /** Override the connection (used by tests). */
    public static function setPdo(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public static function all(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function one(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<int|string, mixed> $params */
    public static function run(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** UTC timestamp in a format both SQLite and MySQL sort correctly. */
    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
