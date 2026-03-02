<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Integration\Database;

use Modular\Persistence\Database\PostgresDatabase;
use Modular\Persistence\Database\TransactionManager;
use Modular\Persistence\Tests\Integration\Support\ConnectionHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for transaction lifecycle: commit, rollback, isolation.
 *
 * Extends TestCase directly (not PostgresTestCase) because we need explicit
 * control over transactions rather than the automatic BEGIN/ROLLBACK isolation.
 */
#[CoversClass(TransactionManager::class)]
#[CoversClass(PostgresDatabase::class)]
final class TransactionTest extends TestCase
{
    use ConnectionHelper;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        try {
            $db = static::connect();
        } catch (\PDOException $e) {
            static::markTestSkipped('PostgreSQL is not available: ' . $e->getMessage());
        }

        $db->exec('DROP TABLE IF EXISTS "test_tx" CASCADE');
        $db->exec('CREATE TABLE "test_tx" ("id" SERIAL PRIMARY KEY, "value" TEXT NOT NULL)');
    }

    protected function setUp(): void
    {
        parent::setUp();
        static::connect()->exec('DELETE FROM "test_tx"');
    }

    public function testCommitPersistsData(): void
    {
        $db = static::connect();

        $db->beginTransaction();
        $db->exec("INSERT INTO \"test_tx\" (\"value\") VALUES ('committed')");
        $db->commit();

        $stmt = $db->query('SELECT COUNT(*) as cnt FROM "test_tx"');
        $count = (int) $stmt->fetch()['cnt'];
        self::assertSame(1, $count);
    }

    public function testRollbackRevertsData(): void
    {
        $db = static::connect();

        $db->exec("INSERT INTO \"test_tx\" (\"value\") VALUES ('permanent')");
        self::assertSame(1, $this->getRowCount($db));

        $db->beginTransaction();
        $db->exec("INSERT INTO \"test_tx\" (\"value\") VALUES ('transient')");
        self::assertSame(2, $this->getRowCount($db));

        $db->rollBack();
        self::assertSame(1, $this->getRowCount($db));
    }

    public function testInTransactionState(): void
    {
        $db = static::connect();

        self::assertFalse($db->inTransaction());

        $db->beginTransaction();
        self::assertTrue($db->inTransaction());

        $db->commit();
        self::assertFalse($db->inTransaction());
    }

    public function testTransactionIsolatesReads(): void
    {
        $db = static::connect();
        $db->exec("INSERT INTO \"test_tx\" (\"value\") VALUES ('visible')");

        $db->beginTransaction();
        $db->exec("INSERT INTO \"test_tx\" (\"value\") VALUES ('in-tx-only')");

        // Inside the transaction, we can see both rows
        self::assertSame(2, $this->getRowCount($db));

        $db->rollBack();

        // After rollback, only the committed row remains
        self::assertSame(1, $this->getRowCount($db));
    }

    public function testPrepareWithinTransaction(): void
    {
        $db = static::connect();

        $db->beginTransaction();

        $stmt = $db->prepare("INSERT INTO \"test_tx\" (\"value\") VALUES (:val)");
        $stmt->execute(['val' => 'prepared-in-tx']);

        $db->commit();

        $stmt = $db->query('SELECT "value" FROM "test_tx" WHERE "value" = \'prepared-in-tx\'');
        $row = $stmt->fetch();
        self::assertSame('prepared-in-tx', $row['value']);
    }

    private function getRowCount(PostgresDatabase $db): int
    {
        $stmt = $db->query('SELECT COUNT(*) as cnt FROM "test_tx"');

        return (int) $stmt->fetch()['cnt'];
    }

    public static function tearDownAfterClass(): void
    {
        try {
            static::connect()->exec('DROP TABLE IF EXISTS "test_tx" CASCADE');
        } catch (\PDOException) {
            // Ignore
        }

        static::resetConnection();
        parent::tearDownAfterClass();
    }
}
