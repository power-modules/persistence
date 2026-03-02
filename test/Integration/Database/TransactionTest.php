<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Integration\Database;

use Modular\Persistence\Database\Database;
use Modular\Persistence\Database\PostgresDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for transaction behavior against real PostgreSQL.
 *
 * Tests beginTransaction(), commit(), rollBack(), inTransaction() and
 * verifies actual data persistence/rollback behavior.
 */
#[CoversClass(Database::class)]
#[CoversClass(PostgresDatabase::class)]
class TransactionTest extends TestCase
{
    private static ?PDO $pdo = null;
    private static ?PostgresDatabase $database = null;

    private static function connect(): PostgresDatabase
    {
        if (self::$database !== null) {
            return self::$database;
        }

        $dsn = getenv('DB_DSN') ?: 'pgsql:host=127.0.0.1;port=15432;dbname=persistence_test';
        $user = getenv('DB_USER') ?: 'persistence_test';
        $password = getenv('DB_PASSWORD') ?: 'persistence_test';

        try {
            self::$pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (\PDOException $e) {
            self::markTestSkipped('PostgreSQL is not available: ' . $e->getMessage());
        }

        self::$database = new PostgresDatabase(self::$pdo);

        return self::$database;
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $db = self::connect();
        $db->exec('DROP TABLE IF EXISTS "test_txn" CASCADE');
        $db->exec('CREATE TABLE "test_txn" ("id" VARCHAR(36) PRIMARY KEY, "value" VARCHAR(255) NOT NULL)');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $db = self::connect();
        $db->exec('DELETE FROM "test_txn"');
    }

    public function testCommitPersistsData(): void
    {
        $db = self::connect();

        $db->beginTransaction();
        self::assertTrue($db->inTransaction());

        $db->exec('INSERT INTO "test_txn" ("id", "value") VALUES (\'1\', \'committed\')');
        $db->commit();

        self::assertFalse($db->inTransaction());

        // Data should be visible
        $stmt = $db->query('SELECT * FROM "test_txn"');
        $rows = $stmt->fetchAll();
        self::assertCount(1, $rows);
        self::assertSame('committed', $rows[0]['value']);
    }

    public function testRollbackRevertsData(): void
    {
        $db = self::connect();

        // Insert baseline data outside transaction
        $db->exec('INSERT INTO "test_txn" ("id", "value") VALUES (\'0\', \'baseline\')');

        $db->beginTransaction();
        $db->exec('INSERT INTO "test_txn" ("id", "value") VALUES (\'1\', \'rolled_back\')');

        // Within transaction, both rows visible
        $stmt = $db->query('SELECT * FROM "test_txn"');
        self::assertCount(2, $stmt->fetchAll());

        $db->rollBack();

        // After rollback, only baseline remains
        $stmt = $db->query('SELECT * FROM "test_txn"');
        $rows = $stmt->fetchAll();
        self::assertCount(1, $rows);
        self::assertSame('baseline', $rows[0]['value']);
    }

    public function testInTransactionState(): void
    {
        $db = self::connect();

        self::assertFalse($db->inTransaction());

        $db->beginTransaction();
        self::assertTrue($db->inTransaction());

        $db->commit();
        self::assertFalse($db->inTransaction());

        $db->beginTransaction();
        self::assertTrue($db->inTransaction());

        $db->rollBack();
        self::assertFalse($db->inTransaction());
    }

    public function testTransactionIsolatesReads(): void
    {
        $db = self::connect();

        // Insert data in a transaction
        $db->beginTransaction();
        $db->exec('INSERT INTO "test_txn" ("id", "value") VALUES (\'1\', \'in_txn\')');

        // Data is visible within the same connection's transaction
        $stmt = $db->query('SELECT COUNT(*) as cnt FROM "test_txn"');
        self::assertSame(1, (int) $stmt->fetch()['cnt']);

        $db->rollBack();

        // After rollback, data is gone
        $stmt = $db->query('SELECT COUNT(*) as cnt FROM "test_txn"');
        self::assertSame(0, (int) $stmt->fetch()['cnt']);
    }

    public function testPrepareWithinTransaction(): void
    {
        $db = self::connect();

        $db->beginTransaction();

        $stmt = $db->prepare('INSERT INTO "test_txn" ("id", "value") VALUES (:id, :value)');
        $stmt->execute(['id' => '1', 'value' => 'prepared_in_txn']);

        $db->commit();

        $stmt = $db->query('SELECT * FROM "test_txn" WHERE "id" = \'1\'');
        $row = $stmt->fetch();
        self::assertSame('prepared_in_txn', $row['value']);
    }

    public static function tearDownAfterClass(): void
    {
        try {
            $db = self::connect();
            $db->exec('DROP TABLE IF EXISTS "test_txn" CASCADE');
        } catch (\PDOException) {
            // Ignore
        }

        self::$database = null;
        self::$pdo = null;

        parent::tearDownAfterClass();
    }
}
