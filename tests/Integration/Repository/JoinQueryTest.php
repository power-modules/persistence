<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Integration\Repository;

use Modular\Persistence\Repository\Join;
use Modular\Persistence\Repository\JoinType;
use Modular\Persistence\Repository\Statement\SelectStatement;
use Modular\Persistence\Tests\Integration\Support\ConnectionHelper;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

/**
 * Integration tests for JOIN with safe type-cast expressions (NULLIF + ::type).
 *
 * Tests that JOIN with localKeyType correctly generates NULLIF("table"."col", '')::type
 * and that empty strings are safely converted to NULL before casting to types like UUID.
 *
 * Extends TestCase directly because it manages ad-hoc tables without ISchema enums.
 */
#[CoversClass(SelectStatement::class)]
#[CoversClass(Join::class)]
final class JoinQueryTest extends TestCase
{
    use ConnectionHelper;

    private string $engineeringDeptId = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        try {
            $db = static::connect();
        } catch (\PDOException $e) {
            static::markTestSkipped('PostgreSQL is not available: ' . $e->getMessage());
        }

        $db->exec('DROP TABLE IF EXISTS "test_assignment" CASCADE');
        $db->exec('DROP TABLE IF EXISTS "test_department" CASCADE');

        $db->exec('CREATE TABLE "test_department" (
            "id" UUID NOT NULL PRIMARY KEY,
            "name" VARCHAR(255) NOT NULL
        )');

        $db->exec('CREATE TABLE "test_assignment" (
            "id" UUID NOT NULL PRIMARY KEY,
            "person_name" VARCHAR(255) NOT NULL,
            "department_id" VARCHAR(255) NOT NULL DEFAULT \'\'
        )');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $db = static::connect();
        $db->exec('DELETE FROM "test_assignment"');
        $db->exec('DELETE FROM "test_department"');

        $stmt = $db->prepare('INSERT INTO "test_department" ("id", "name") VALUES (:id, :name)');
        $deptId = Uuid::uuid7()->toString();
        $stmt->execute(['id' => $deptId, 'name' => 'Engineering']);
        $this->engineeringDeptId = $deptId;

        $stmt = $db->prepare('INSERT INTO "test_assignment" ("id", "person_name", "department_id") VALUES (:id, :name, :dept)');
        $stmt->execute(['id' => Uuid::uuid7()->toString(), 'name' => 'Alice', 'dept' => $deptId]);
        $stmt->execute(['id' => Uuid::uuid7()->toString(), 'name' => 'Bob', 'dept' => '']);
    }

    public function testJoinWithLocalKeyTypeCastUuid(): void
    {
        self::assertNotEmpty($this->engineeringDeptId);

        $db = static::connect();

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

    public function testInnerJoinWithCastExcludesEmptyStrings(): void
    {
        $db = static::connect();

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
            $db = static::connect();
            $db->exec('DROP TABLE IF EXISTS "test_assignment" CASCADE');
            $db->exec('DROP TABLE IF EXISTS "test_department" CASCADE');
        } catch (\PDOException) {
            // Ignore
        }

        static::resetConnection();
        parent::tearDownAfterClass();
    }
}
