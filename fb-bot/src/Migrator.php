<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Migrator
{
    /** @return int[] versions applied in this call */
    public static function run(PDO $pdo): array
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            version INTEGER NOT NULL PRIMARY KEY,
            applied_at TEXT NOT NULL
        )');

        $done = array_map('intval', $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
        $migrations = require BASE_PATH . '/database/migrations.php';
        ksort($migrations);

        $applied = [];
        foreach ($migrations as $version => $statements) {
            if (in_array($version, $done, true)) {
                continue;
            }

            $pdo->beginTransaction();
            try {
                foreach ($statements as $sql) {
                    $pdo->exec(str_replace('{ID}', self::idColumn($pdo), $sql));
                }
                $stmt = $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)');
                $stmt->execute([$version, gmdate('Y-m-d H:i:s')]);
                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            $applied[] = $version;
        }

        return $applied;
    }

    private static function idColumn(PDO $pdo): string
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    }

    /** @return string[] */
    public static function tables(PDO $pdo): array
    {
        return $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
            ->fetchAll(PDO::FETCH_COLUMN);
    }
}
