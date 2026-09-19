<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * PDO সংযোগ ও কাঁচা কোয়েরি হেল্পার। সব কোয়েরি prepared statement.
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static int $transactionDepth = 0;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = Config::get('database');
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('ডেটাবেসে সংযোগ করা যায়নি: ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }

    /** ইনস্টলার dbname ছাড়া সংযোগ করে ডেটাবেস তৈরির জন্য এটি ব্যবহার করে। */
    public static function connectServer(array $config): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', $config['host'], $config['port'], $config['charset'] ?? 'utf8mb4');

        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    public static function useConnection(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function isConnected(): bool
    {
        return self::$pdo instanceof PDO;
    }

    public static function run(string $sql, array $bindings = []): PDOStatement
    {
        $statement = self::pdo()->prepare($sql);
        $statement->execute(self::normalise($bindings));

        return $statement;
    }

    public static function select(string $sql, array $bindings = []): array
    {
        return self::run($sql, $bindings)->fetchAll();
    }

    public static function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = self::run($sql, $bindings)->fetch();

        return $row === false ? null : $row;
    }

    public static function scalar(string $sql, array $bindings = []): mixed
    {
        $value = self::run($sql, $bindings)->fetchColumn();

        return $value === false ? null : $value;
    }

    public static function statement(string $sql, array $bindings = []): int
    {
        return self::run($sql, $bindings)->rowCount();
    }

    public static function lastInsertId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    public static function driver(): string
    {
        return (string) self::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * ট্রানজ্যাকশনের ভেতরে একটি রো লক করে পড়ে।
     *
     * দুজন এজেন্ট একই মুহূর্তে একই টিকেট ট্রান্সফার করলে যাতে একজনের
     * কাজ অন্যজনেরটা মুছে না দেয়। SQLite-এ row-level lock নেই (পুরো
     * ডেটাবেসই লক হয়), তাই সেখানে সাধারণ SELECT-ই যথেষ্ট — টেস্ট
     * SQLite-এ চলে বলে এই পার্থক্যটা এখানেই সামলানো।
     */
    public static function lockRow(string $table, int $id): ?array
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new RuntimeException("অবৈধ টেবিল নাম: {$table}");
        }

        $sql = "SELECT * FROM `{$table}` WHERE id = ?";
        if (self::driver() === 'mysql') {
            $sql .= ' FOR UPDATE';
        }

        return self::selectOne($sql, [$id]);
    }

    /**
     * নেস্টেড কল সেভপয়েন্ট ছাড়াই নিরাপদ — ভেতরের কল বাইরের ট্রানজ্যাকশনেই যোগ হয়।
     */
    public static function transaction(callable $callback): mixed
    {
        self::beginTransaction();

        try {
            $result = $callback();
            self::commit();

            return $result;
        } catch (\Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    public static function beginTransaction(): void
    {
        if (self::$transactionDepth === 0) {
            self::pdo()->beginTransaction();
        }
        self::$transactionDepth++;
    }

    public static function commit(): void
    {
        self::$transactionDepth--;
        if (self::$transactionDepth === 0 && self::pdo()->inTransaction()) {
            self::pdo()->commit();
        }
    }

    public static function rollBack(): void
    {
        self::$transactionDepth = 0;
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }

    /** bool → int, DateTimeInterface → MySQL DATETIME. */
    private static function normalise(array $bindings): array
    {
        foreach ($bindings as $key => $value) {
            if (is_bool($value)) {
                $bindings[$key] = $value ? 1 : 0;
            } elseif ($value instanceof \DateTimeInterface) {
                $bindings[$key] = $value->format('Y-m-d H:i:s');
            }
        }

        return $bindings;
    }
}
