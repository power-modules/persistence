<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Integration\Database;

use Modular\Persistence\Database\NamespaceAwarePostgresDatabase;
use Modular\Persistence\Database\PostgresDatabase;
use Modular\Persistence\Repository\Statement\Provider\RuntimeNamespaceProvider;
use Modular\Persistence\Tests\Integration\Support\ConnectionHelper;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for PostgreSQL multi-tenancy via search_path.
 *
 * Tests schema namespace switching, tenant data isolation, namespace caching,
 * rollback cache invalidation, and the NamespaceAwarePostgresDatabase decorator.
 *
 * Extends TestCase directly because it needs explicit schema/DDL management.
 */
#[CoversClass(PostgresDatabase::class)]
#[CoversClass(NamespaceAwarePostgresDatabase::class)]
final class MultiTenancyTest extends TestCase
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

        // Create tenant schemas with identical tables
        $db->exec('DROP SCHEMA IF EXISTS tenant_a CASCADE');
        $db->exec('DROP SCHEMA IF EXISTS tenant_b CASCADE');
        $db->exec('CREATE SCHEMA tenant_a');
        $db->exec('CREATE SCHEMA tenant_b');
        $db->exec('CREATE TABLE tenant_a.items ("id" VARCHAR(36) PRIMARY KEY, "name" TEXT NOT NULL)');
        $db->exec('CREATE TABLE tenant_b.items ("id" VARCHAR(36) PRIMARY KEY, "name" TEXT NOT NULL)');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $db = static::connect();
        $db->exec('DELETE FROM tenant_a.items');
        $db->exec('DELETE FROM tenant_b.items');
        $db->setSearchPath('public');
    }

    public function testUseNamespaceSetsSearchPath(): void
    {
        $db = static::connect();
        $db->useNamespace('tenant_a');

        self::assertSame('tenant_a', $db->getSearchPath());
    }

    public function testSetSearchPathSinglePath(): void
    {
        $db = static::connect();
        $db->setSearchPath('tenant_b');

        self::assertSame('tenant_b', $db->getSearchPath());
    }

    public function testSetSearchPathMultiplePaths(): void
    {
        $db = static::connect();
        $db->setSearchPath('tenant_a, public');

        self::assertSame('tenant_a, public', $db->getSearchPath());
    }

    public function testTenantDataIsolation(): void
    {
        $db = static::connect();

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
        $db = static::connect();

        $db->useNamespace('tenant_a');
        self::assertSame('tenant_a', $db->getSearchPath());

        // Second call with same namespace should be a no-op (cached)
        $db->useNamespace('tenant_a');
        self::assertSame('tenant_a', $db->getSearchPath());

        // Changing to different namespace should work
        $db->useNamespace('tenant_b');
        self::assertSame('tenant_b', $db->getSearchPath());
    }

    public function testRollbackInvalidatesNamespaceCache(): void
    {
        $db = static::connect();

        $db->useNamespace('tenant_a');
        self::assertSame('tenant_a', $db->getSearchPath());

        $db->beginTransaction();
        $db->useNamespace('tenant_b');
        self::assertSame('tenant_b', $db->getSearchPath());

        $db->rollBack();

        // After rollback, cache was cleared — useNamespace re-issues SET search_path
        $db->useNamespace('tenant_a');
        self::assertSame('tenant_a', $db->getSearchPath());
    }

    public function testNamespaceAwareDecoratorAutoSetsNamespace(): void
    {
        $db = static::connect();
        $provider = new RuntimeNamespaceProvider();
        $provider->setNamespace('tenant_a');

        $nsDb = new NamespaceAwarePostgresDatabase($db, $provider);

        $nsDb->exec('INSERT INTO items ("id", "name") VALUES (\'10\', \'Auto NS Item\')');

        $stmt = $nsDb->query('SELECT * FROM items');
        $rows = $stmt->fetchAll();
        self::assertCount(1, $rows);
        self::assertSame('Auto NS Item', $rows[0]['name']);

        // Switch provider to tenant_b — tenant_b should be empty
        $provider->setNamespace('tenant_b');
        $stmt = $nsDb->query('SELECT * FROM items');
        $rows = $stmt->fetchAll();
        self::assertCount(0, $rows);
    }

    public function testNamespaceAwareDecoratorWithPrepare(): void
    {
        $db = static::connect();
        $provider = new RuntimeNamespaceProvider();
        $provider->setNamespace('tenant_a');

        $nsDb = new NamespaceAwarePostgresDatabase($db, $provider);

        $stmt = $nsDb->prepare('INSERT INTO items ("id", "name") VALUES (:id, :name)');
        $stmt->execute(['id' => '20', 'name' => 'Prepared Item']);

        $stmt = $nsDb->prepare('SELECT * FROM items WHERE "id" = :id');
        $stmt->execute(['id' => '20']);
        $row = $stmt->fetch();

        self::assertSame('Prepared Item', $row['name']);
    }

    public function testNamespaceAwareDecoratorEmptyNamespaceDoesNotSwitch(): void
    {
        $db = static::connect();
        $provider = new RuntimeNamespaceProvider();
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
        $db = static::connect();

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
        $db = static::connect();

        $result = $db->pgsqlGetNotify(PDO::FETCH_ASSOC, 0);

        self::assertFalse($result);
    }

    public static function tearDownAfterClass(): void
    {
        try {
            $db = static::connect();
            $db->exec('DROP SCHEMA IF EXISTS tenant_a CASCADE');
            $db->exec('DROP SCHEMA IF EXISTS tenant_b CASCADE');
            $db->setSearchPath('public');
        } catch (\PDOException) {
            // Ignore
        }

        static::resetConnection();
        parent::tearDownAfterClass();
    }
}
