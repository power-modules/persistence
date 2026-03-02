<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Integration\Support;

use Modular\Persistence\Database\PostgresDatabase;
use Modular\Persistence\Schema\Adapter\PostgresSchemaQueryGenerator;
use Modular\Persistence\Schema\Contract\ISchema;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base class for integration tests that need a real PostgreSQL connection.
 *
 * Uses transaction-based isolation: setUp() begins a transaction, tearDown() rolls it back.
 * DDL (table creation) runs once in setUpBeforeClass() — PostgreSQL supports transactional DDL
 * but CREATE TABLE is only executed once per class for performance.
 *
 * Subclasses must implement getSchemas() to declare which schemas to create tables for.
 */
abstract class PostgresTestCase extends TestCase
{
    protected static ?PDO $pdo = null;
    protected static ?PostgresDatabase $database = null;

    protected static function getConnection(): PostgresDatabase
    {
        if (self::$database !== null) {
            return self::$database;
        }

        $dsn = getenv('DB_DSN') ?: 'pgsql:host=127.0.0.1;port=15432;dbname=persistence_test';
        $user = getenv('DB_USER') ?: 'persistence_test';
        $password = getenv('DB_PASSWORD') ?: 'persistence_test';

        self::$pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        self::$database = new PostgresDatabase(self::$pdo);

        return self::$database;
    }

    /**
     * @return array<ISchema> One representative case per schema enum (used to get table name + generate DDL).
     */
    abstract protected static function getSchemas(): array;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        try {
            $db = static::getConnection();
        } catch (\PDOException $e) {
            static::markTestSkipped('PostgreSQL is not available: ' . $e->getMessage());
        }

        $generator = new PostgresSchemaQueryGenerator();

        foreach (static::getSchemas() as $schema) {
            $db->exec(sprintf('DROP TABLE IF EXISTS "%s" CASCADE', $schema::getTableName()));

            foreach ($generator->generate($schema) as $query) {
                $db->exec($query);
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        try {
            static::getConnection()->beginTransaction();
        } catch (\PDOException $e) {
            static::markTestSkipped('PostgreSQL is not available: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        $db = static::getConnection();

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        $db = static::getConnection();

        foreach (static::getSchemas() as $schema) {
            $db->exec(sprintf('DROP TABLE IF EXISTS "%s" CASCADE', $schema::getTableName()));
        }

        self::$database = null;
        self::$pdo = null;

        parent::tearDownAfterClass();
    }
}
