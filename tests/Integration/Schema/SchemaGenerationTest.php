<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Integration\Schema;

use Modular\Persistence\Exception\QueryException;
use Modular\Persistence\Schema\Adapter\PostgresSchemaQueryGenerator;
use Modular\Persistence\Tests\Integration\Fixture\EmployeeSchema;
use Modular\Persistence\Tests\Integration\Fixture\ProductSchema;
use Modular\Persistence\Tests\Integration\Support\ConnectionHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests that execute PostgresSchemaQueryGenerator DDL against real PostgreSQL.
 *
 * Verifies that generated CREATE TABLE, CREATE INDEX, ALTER TABLE statements
 * are valid PostgreSQL syntax and produce the expected schema objects.
 */
#[CoversClass(PostgresSchemaQueryGenerator::class)]
final class SchemaGenerationTest extends TestCase
{
    use ConnectionHelper;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            static::connect();
        } catch (\PDOException $e) {
            self::markTestSkipped('PostgreSQL is not available: ' . $e->getMessage());
        }
    }

    private function dropTable(string $tableName): void
    {
        static::connect()->exec(sprintf('DROP TABLE IF EXISTS "%s" CASCADE', $tableName));
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = static::connect()->prepare(
            "SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = :name AND table_schema = 'public')",
        );
        $stmt->execute(['name' => $tableName]);

        return (bool) $stmt->fetch()['exists'];
    }

    private function indexExists(string $indexName): bool
    {
        $stmt = static::connect()->prepare(
            "SELECT EXISTS (SELECT 1 FROM pg_indexes WHERE indexname = :name AND schemaname = 'public')",
        );
        $stmt->execute(['name' => $indexName]);

        return (bool) $stmt->fetch()['exists'];
    }

    /**
     * @return array<string>
     */
    private function getColumnNames(string $tableName): array
    {
        $stmt = static::connect()->prepare(
            "SELECT column_name FROM information_schema.columns WHERE table_name = :name AND table_schema = 'public' ORDER BY ordinal_position",
        );
        $stmt->execute(['name' => $tableName]);

        return array_column($stmt->fetchAll(), 'column_name');
    }

    // ── Employee schema (basic CREATE TABLE) ─────────────────────────

    public function testGenerateEmployeeSchema(): void
    {
        $this->dropTable(EmployeeSchema::getTableName());

        $generator = new PostgresSchemaQueryGenerator();

        foreach ($generator->generate(EmployeeSchema::Id) as $query) {
            static::connect()->exec($query);
        }

        self::assertTrue($this->tableExists(EmployeeSchema::getTableName()));

        $columns = $this->getColumnNames(EmployeeSchema::getTableName());
        self::assertContains('id', $columns);
        self::assertContains('name', $columns);
        self::assertContains('created_at', $columns);
        self::assertContains('deleted_at', $columns);

        // Verify insert/select works on created table
        $db = static::connect();
        $stmt = $db->prepare(sprintf(
            'INSERT INTO "%s" ("id", "name", "created_at") VALUES (:id, :name, :created_at)',
            EmployeeSchema::getTableName(),
        ));
        $stmt->execute(['id' => 'test-1', 'name' => 'Test', 'created_at' => '2024-01-01 00:00:00+00']);

        $stmt = $db->query(sprintf('SELECT * FROM "%s"', EmployeeSchema::getTableName()));
        $rows = $stmt->fetchAll();
        self::assertCount(1, $rows);

        $this->dropTable(EmployeeSchema::getTableName());
    }

    // ── Product schema (JSONB columns + GIN index) ───────────────────

    public function testGenerateProductSchemaWithGinIndex(): void
    {
        $this->dropTable(ProductSchema::getTableName());

        $generator = new PostgresSchemaQueryGenerator();

        foreach ($generator->generate(ProductSchema::Id) as $query) {
            static::connect()->exec($query);
        }

        self::assertTrue($this->tableExists(ProductSchema::getTableName()));

        $columns = $this->getColumnNames(ProductSchema::getTableName());
        self::assertContains('id', $columns);
        self::assertContains('name', $columns);
        self::assertContains('metadata', $columns);
        self::assertContains('tags', $columns);
        self::assertContains('created_at', $columns);

        // Verify GIN index was created
        self::assertTrue($this->indexExists('idx_test_product_metadata_gin'));

        // Verify JSONB operations work on created table
        $db = static::connect();
        $stmt = $db->prepare(sprintf(
            'INSERT INTO "%s" ("id", "name", "metadata", "tags", "created_at") VALUES (:id, :name, :metadata, :tags, :created_at)',
            ProductSchema::getTableName(),
        ));
        $stmt->execute([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'Test Product',
            'metadata' => '{"key": "value"}',
            'tags' => '["tag1", "tag2"]',
            'created_at' => '2024-01-01 00:00:00+00',
        ]);

        $stmt = $db->prepare(sprintf(
            'SELECT * FROM "%s" WHERE "metadata" @> :filter::jsonb',
            ProductSchema::getTableName(),
        ));
        $stmt->execute(['filter' => '{"key": "value"}']);
        $rows = $stmt->fetchAll();
        self::assertCount(1, $rows);

        $this->dropTable(ProductSchema::getTableName());
    }

    // ── Custom table name ────────────────────────────────────────────

    public function testGenerateWithCustomTableName(): void
    {
        $customName = 'custom_employee_table';
        $this->dropTable($customName);

        $generator = new PostgresSchemaQueryGenerator();

        foreach ($generator->generate(EmployeeSchema::Id, $customName) as $query) {
            static::connect()->exec($query);
        }

        self::assertTrue($this->tableExists($customName));

        $this->dropTable($customName);
    }

    // ── ALTER ADD COLUMN ─────────────────────────────────────────────

    public function testGenerateAlterAddColumn(): void
    {
        $this->dropTable(EmployeeSchema::getTableName());

        $generator = new PostgresSchemaQueryGenerator();

        foreach ($generator->generate(EmployeeSchema::Id) as $query) {
            static::connect()->exec($query);
        }

        // ALTER ADD COLUMN should fail because the column already exists
        // but the SQL syntax should be valid PostgreSQL
        $alterSql = $generator->generateAlterAddColumn(EmployeeSchema::Name);

        try {
            static::connect()->exec($alterSql);
            self::fail('Expected duplicate column error');
        } catch (QueryException $e) {
            self::assertStringContainsString('already exists', $e->getMessage());
        }

        $this->dropTable(EmployeeSchema::getTableName());
    }

    public static function tearDownAfterClass(): void
    {
        try {
            $db = static::connect();
            $db->exec(sprintf('DROP TABLE IF EXISTS "%s" CASCADE', EmployeeSchema::getTableName()));
            $db->exec(sprintf('DROP TABLE IF EXISTS "%s" CASCADE', ProductSchema::getTableName()));
            $db->exec('DROP TABLE IF EXISTS "custom_employee_table" CASCADE');
        } catch (\PDOException) {
            // Ignore
        }

        static::resetConnection();
        parent::tearDownAfterClass();
    }
}
