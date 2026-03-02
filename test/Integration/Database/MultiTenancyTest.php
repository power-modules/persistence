<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Integration\Database;

use Modular\Persistence\Database\NamespaceAwarePostgresDatabase;
use Modular\Persistence\Database\PostgresDatabase;
use Modular\Persistence\Repository\Statement\Provider\RuntimeNamespaceProvider;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for PostgreSQL search_path / namespace multi-tenancy.
 *
 * Tests PostgresDatabase::useNamespace(), getSearchPath(), setSearchPath()
 * and NamespaceAwarePostgresDatabase decorator against a real PostgreSQL instance.
 *
 * Cannot use PostgresTestCase (transaction-based isolation) because we need to test
 * schema-level DDL and explicit transaction behavior.
 */
#[CoversClass(PostgresDatabase::class)]
#[CoversClass(NamespaceAwarePostgresDatabase::class)]
class MultiTenancyTest extends TestCase
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

        // Create two tenant schemas with identical tables
        $db->exec('DROP SCHEMA IF EXISTS tenant_a CASCADE');
        $db->exec('DROP SCHEMA IF EXISTS tenant_b CASCADE');
        $db->exec('CREATE SCHEMA tenant_a');
        $db->exec('CREATE SCHEMA tenant_b');
        $db->exec('CREATE TABLE tenant_a.items ("id" VARCHAR(36) PRIMARY KEY, "name" VARCHAR(255) NOT NULL)');
        $db->exec('CREATE TABLE tenant_b.items ("id" VARCHAR(36) PRIMARY KEY, "name" VARCHAR(255) NOT NULL)');

        // Reset search_path to public
        $db->setSearchPath('public');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $db = self::connect();

        // Clean tenant data between tests
        $db->exec('DELETE FROM tenant_a.items');
        $db->exec('DELETE FROM tenant_b.items');
        $db->setSearchPath('public');
    }

    public function testUseNamespaceSetsSearchPath(): void
    {
        $db = self::connect();

        $db->useNamespace('tenant_a');
        $path = $db->getSearchPath();
        self::assertSame('tenant_a', $path);
    }

    public function testSetSearchPathSinglePath(): void
    {
        $db = self::connect();

        $db->setSearchPath('tenant_b');
        $path = $db->getSearchPath();
        self::assertSame('tenant_b', $path);
    }

    public function testSetSearchPathMultiplePaths(): void
    {
        $db = self::connect();

        $db->setSearchPath('tenant_a, public');
        $path = $db->getSearchPath();
        self::assertStringContainsString('tenant_a', $path);
    }

    public function testTenantDataIsolation(): void
    {
        $db = self::connect();

        // Insert into tenant_a
        $db->useNamespace('tenant_a');
        $db->exec('INSERT INTO items ("id", "name") VALUES (\'1\', \'Tenant A Item\')');

        // Insert into tenant_b
        $db->useNamespace('tenant_b');
        $db->exec('INSERT INTO items ("id", "name") VALUES (\'2\', \'Tenant B Item\')');

        // Verify tenant_a only sees its data
        $db->useNamespace('tenant_a');
        $stmt = $db->query('SELECT * FROM items');
        $rows = $stmt->fetchAll();
        self::assertCount(1, $rows);
        self::assertSame('Tenant A Item', $rows[0]['name']);

        // Verify tenant_b only sees its data
        $db->useNamespace('tenant_b');
        $stmt = $db->query('SELECT * FROM items');
        $rows = $stmt->fetchAll();
        self::assertCount(1, $rows);
        self::assertSame('Tenant B Item', $rows[0]['name']);
    }

    public function testNamespaceCachingPreventsRedundantQueries(): void
    {
        $db = self::connect();

        // First call sets the namespace
        $db->useNamespace('tenant_a');
        self::assertSame('tenant_a', $db->getSearchPath());

        // Second call with same namespace should be a no-op (cached)
        // We can't directly verify no SQL was sent, but we can verify correctness
        $db->useNamespace('tenant_a');
        self::assertSame('tenant_a', $db->getSearchPath());

        // Changing to different namespace should work
        $db->useNamespace('tenant_b');
        self::assertSame('tenant_b', $db->getSearchPath());
    }

    public function testRollbackInvalidatesNamespaceCache(): void
    {
        $db = self::connect();

        $db->useNamespace('tenant_a');
        self::assertSame('tenant_a', $db->getSearchPath());

        $db->beginTransaction();
        $db->useNamespace('tenant_b');
        self::assertSame('tenant_b', $db->getSearchPath());

        // Rollback should invalidate the cache
        $db->rollBack();

        // After rollback, the namespace should be re-sent on next call
        // (cache was cleared, so useNamespace will issue SET search_path again)
        $db->useNamespace('tenant_a');
        self::assertSame('tenant_a', $db->getSearchPath());
    }

    public function testNamespaceAwareDecoratorAutoSetsNamespace(): void
    {
        $db = self::connect();
        $provider = new RuntimeNamespaceProvider();
        $provider->setNamespace('tenant_a');

        $nsDb = new NamespaceAwarePostgresDatabase($db, $provider);

        // Insert data via the namespace-aware decorator
        $nsDb->exec('INSERT INTO items ("id", "name") VALUES (\'10\', \'Auto NS Item\')');

        // Verify it went to tenant_a
        $stmt = $nsDb->query('SELECT * FROM items');
        $rows = $stmt->fetchAll();
        self::assertCount(1, $rows);
        self::assertSame('Auto NS Item', $rows[0]['name']);

        // Switch provider to tenant_b
        $provider->setNamespace('tenant_b');

        // tenant_b should be empty
        $stmt = $nsDb->query('SELECT * FROM items');
        $rows = $stmt->fetchAll();
        self::assertCount(0, $rows);
    }

    public function testNamespaceAwareDecoratorWithPrepare(): void
    {
        $db = self::connect();
        $provider = new RuntimeNamespaceProvider();
        $provider->setNamespace('tenant_a');

        $nsDb = new NamespaceAwarePostgresDatabase($db, $provider);

        // Use prepare() — should auto-set namespace before preparing
        $stmt = $nsDb->prepare('INSERT INTO items ("id", "name") VALUES (:id, :name)');
        $stmt->execute(['id' => '20', 'name' => 'Prepared Item']);

        $stmt = $nsDb->prepare('SELECT * FROM items WHERE "id" = :id');
        $stmt->execute(['id' => '20']);
        $row = $stmt->fetch();

        self::assertSame('Prepared Item', $row['name']);
    }

    public function testNamespaceAwareDecoratorEmptyNamespaceDoesNotSwitch(): void
    {
        $db = self::connect();
        $provider = new RuntimeNamespaceProvider();
        // Empty namespace — decorator should not call useNamespace()
        $provider->setNamespace('');

        $nsDb = new NamespaceAwarePostgresDatabase($db, $provider);

        // Manually set to tenant_a first
        $db->useNamespace('tenant_a');
        $db->exec('INSERT INTO items ("id", "name") VALUES (\'30\', \'Stays in A\')');

        // With empty namespace provider, should stay on tenant_a
        $stmt = $nsDb->query('SELECT * FROM items');
        $rows = $stmt->fetchAll();
        self::assertCount(1, $rows);
        self::assertSame('Stays in A', $rows[0]['name']);
    }

    public function testPgsqlGetNotifyReturnsNotification(): void
    {
        $db = self::connect();

        // LISTEN on a channel, then NOTIFY it
        $db->exec('LISTEN test_channel');
        $db->exec("NOTIFY test_channel, 'hello'");

        $result = $db->pgsqlGetNotify(PDO::FETCH_ASSOC, 1_000);

        self::assertIsArray($result);
        self::assertSame('test_channel', $result['message']);
        self::assertSame('hello', $result['payload']);

        $db->exec('UNLISTEN test_channel');
    }

    public function testPgsqlGetNotifyReturnsFalseWhenNoNotification(): void
    {
        $db = self::connect();

        // No LISTEN/NOTIFY — should return false immediately
        $result = $db->pgsqlGetNotify(PDO::FETCH_ASSOC, 0);

        self::assertFalse($result);
    }

    public static function tearDownAfterClass(): void
    {
        try {
            $db = self::connect();
            $db->exec('DROP SCHEMA IF EXISTS tenant_a CASCADE');
            $db->exec('DROP SCHEMA IF EXISTS tenant_b CASCADE');
            $db->setSearchPath('public');
        } catch (\PDOException) {
            // Ignore
        }

        self::$database = null;
        self::$pdo = null;

        parent::tearDownAfterClass();
    }
}
