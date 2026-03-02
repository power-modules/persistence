<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Integration\Support;

use Modular\Persistence\Database\PostgresDatabase;
use PDO;

/**
 * Shared connection helper for integration tests that extend TestCase directly
 * and manage their own lifecycle (TransactionTest, MultiTenancyTest, etc).
 */
trait ConnectionHelper
{
    protected static ?PDO $pdo = null;
    protected static ?PostgresDatabase $database = null;

    protected static function connect(): PostgresDatabase
    {
        if (static::$database !== null) {
            return static::$database;
        }

        $dsn = getenv('DB_DSN') ?: 'pgsql:host=127.0.0.1;port=15432;dbname=persistence_test';
        $user = getenv('DB_USER') ?: 'persistence_test';
        $password = getenv('DB_PASSWORD') ?: 'persistence_test';

        try {
            static::$pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (\PDOException $e) {
            static::markTestSkipped('PostgreSQL is not available: ' . $e->getMessage());
        }

        static::$database = new PostgresDatabase(static::$pdo);

        return static::$database;
    }

    protected static function resetConnection(): void
    {
        static::$database = null;
        static::$pdo = null;
    }
}
