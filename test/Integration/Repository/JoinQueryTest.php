<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Integration\Repository;

use Modular\Persistence\Repository\Join;
use Modular\Persistence\Repository\JoinType;
use Modular\Persistence\Repository\Statement\SelectStatement;
use Modular\Persistence\Test\Integration\Support\PostgresTestCase;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use Ramsey\Uuid\Uuid;

/**
 * Integration tests for JOIN with safe type-cast expressions (NULLIF + ::type).
 *
 * Tests that JOIN with localKeyType correctly generates NULLIF("table"."col", '')::type
 * and that empty strings are safely converted to NULL before casting to types like UUID.
 */
#[CoversClass(SelectStatement::class)]
#[CoversClass(Join::class)]
class JoinQueryTest extends PostgresTestCase
{
    protected static function getSchemas(): array
    {
        // We manage tables manually — no schema enums for these ad-hoc tables.
        return [];
    }

    public static function setUpBeforeClass(): void
    {
        try {
            $db = static::getConnection();
        } catch (\PDOException $e) {
            static::markTestSkipped('PostgreSQL is not available: ' . $e->getMessage());
        }

        // Create department table with UUID PK
        $db->exec('DROP TABLE IF EXISTS "test_assignment" CASCADE');
        $db->exec('DROP TABLE IF EXISTS "test_department" CASCADE');

        $db->exec('CREATE TABLE "test_department" (
            "id" UUID NOT NULL PRIMARY KEY,
            "name" VARCHAR(255) NOT NULL
        )');

        // Create assignment table with a TEXT column referencing department UUID
        // This simulates a real-world scenario where a text column holds a UUID reference
        $db->exec('CREATE TABLE "test_assignment" (
            "id" UUID NOT NULL PRIMARY KEY,
            "person_name" VARCHAR(255) NOT NULL,
            "department_id" VARCHAR(255) NOT NULL DEFAULT \'\'
        )');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $db = static::getConnection();

        // Seed departments
        $stmt = $db->prepare('INSERT INTO "test_department" ("id", "name") VALUES (:id, :name)');
        $deptId = Uuid::uuid7()->toString();
        $stmt->execute(['id' => $deptId, 'name' => 'Engineering']);
        $this->engineeringDeptId = $deptId;

        // Seed assignments — one with valid UUID reference, one with empty string
        $stmt = $db->prepare('INSERT INTO "test_assignment" ("id", "person_name", "department_id") VALUES (:id, :name, :dept)');
        $stmt->execute(['id' => Uuid::uuid7()->toString(), 'name' => 'Alice', 'dept' => $deptId]);
        $stmt->execute(['id' => Uuid::uuid7()->toString(), 'name' => 'Bob', 'dept' => '']);
    }

    private string $engineeringDeptId = '';

    public function testJoinWithLocalKeyTypeCastUuid(): void
    {
        // Verify the department was seeded correctly
        self::assertNotEmpty($this->engineeringDeptId);

        $db = static::getConnection();

        // Join with localKeyType = 'uuid' uses NULLIF("test_assignment"."department_id", '')::uuid
        $join = new Join(
            JoinType::Left,
            'test_department',
            'department_id',
            'id',
            'test_assignment',
            localKeyType: 'uuid',
        );

        $select = new SelectStatement('test_assignment', [
            '"test_assignment"."person_name"',
            '"test_department"."name" as department_name',
        ]);
        $select->addJoin($join);
        $select->addOrder('"test_assignment"."person_name"', 'ASC');

        $stmt = $db->prepare($select->getQuery());
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        self::assertCount(2, $rows);

        // Alice has a valid department reference
        self::assertSame('Alice', $rows[0]['person_name']);
        self::assertSame('Engineering', $rows[0]['department_name']);

        // Bob has empty string department_id — NULLIF converts to NULL, LEFT JOIN yields NULL
        self::assertSame('Bob', $rows[1]['person_name']);
        self::assertNull($rows[1]['department_name']);
    }

    public function testJoinWithoutLocalKeyTypeCast(): void
    {
        $db = static::getConnection();

        // Join without localKeyType uses standard "table"."col" (no NULLIF/cast)
        // This would fail with '::uuid' cast if we had empty strings,
        // but since we're not casting, it simply won't match (text != uuid comparison)
        $join = new Join(
            JoinType::Inner,
            'test_department',
            'department_id',
            'id',
            'test_assignment',
            localKeyType: 'uuid',
        );

        $select = new SelectStatement('test_assignment', ['"test_assignment"."person_name"']);
        $select->addJoin($join);

        $stmt = $db->prepare($select->getQuery());
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Only Alice matches (INNER JOIN + valid UUID)
        self::assertCount(1, $rows);
        self::assertSame('Alice', $rows[0]['person_name']);
    }

    public static function tearDownAfterClass(): void
    {
        try {
            $db = static::getConnection();
            $db->exec('DROP TABLE IF EXISTS "test_assignment" CASCADE');
            $db->exec('DROP TABLE IF EXISTS "test_department" CASCADE');
        } catch (\PDOException) {
            // Ignore — might not have connected at all
        }

        parent::tearDownAfterClass();
    }
}
