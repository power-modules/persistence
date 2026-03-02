<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Unit\Repository\Statement;

use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Join;
use Modular\Persistence\Repository\JoinType;
use Modular\Persistence\Repository\Statement\SelectStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Expanded from original: adds ORDER BY, GROUP BY, LIMIT, all(), count() tests
 * on top of the existing join and namespace coverage.
 */
#[CoversClass(SelectStatement::class)]
final class SelectStatementTest extends TestCase
{
    // ── Join SQL ──

    public function testGetQueryIncludesJoin(): void
    {
        $stmt = new SelectStatement('employees');
        $stmt->addJoin(new Join(JoinType::Inner, 'departments', 'dept_id', 'id'));

        $sql = $stmt->getQuery();

        self::assertStringStartsWith('SELECT * FROM "employees"', $sql);
        self::assertStringContainsString(
            'INNER JOIN "departments" ON "departments"."id" = "employees"."dept_id"',
            $sql,
        );
    }

    public function testGetQueryIncludesJoinWithLocalKeyType(): void
    {
        $stmt = new SelectStatement('employees');
        $stmt->addJoin(new Join(JoinType::Inner, 'departments', 'dept_uuid', 'id', localKeyType: 'uuid'));

        $sql = $stmt->getQuery();

        self::assertStringContainsString(
            'NULLIF("employees"."dept_uuid", \'\')::uuid',
            $sql,
        );
    }

    public function testMultipleJoinsMixedLocalKeyType(): void
    {
        $stmt = new SelectStatement('employees');
        $stmt->addJoin(
            new Join(JoinType::Inner, 'departments', 'dept_id', 'id'),
            new Join(JoinType::Left, 'teams', 'team_uuid', 'id', localKeyType: 'uuid'),
        );

        $sql = $stmt->getQuery();

        self::assertStringContainsString(
            'INNER JOIN "departments" ON "departments"."id" = "employees"."dept_id"',
            $sql,
        );
        self::assertStringContainsString(
            'LEFT JOIN "teams" ON "teams"."id" = NULLIF("employees"."team_uuid", \'\')::uuid',
            $sql,
        );
    }

    // ── Namespace ──

    public function testJoinWithNamespace(): void
    {
        $stmt = new SelectStatement('employees', ['*'], 'my_schema');
        $stmt->addJoin(new Join(JoinType::Inner, 'departments', 'dept_uuid', 'id', localKeyType: 'uuid'));

        $sql = $stmt->getQuery();

        self::assertStringContainsString('SELECT * FROM "my_schema"."employees"', $sql);
        self::assertStringContainsString('NULLIF("employees"."dept_uuid", \'\')::uuid', $sql);
    }

    // ── Count ──

    public function testCountQueryIncludesJoinWithLocalKeyType(): void
    {
        $stmt = new SelectStatement('employees');
        $stmt->addJoin(new Join(JoinType::Inner, 'departments', 'dept_uuid', 'id', localKeyType: 'uuid'));

        $sql = $stmt->count();

        self::assertStringContainsString('SELECT COUNT(*) as total_rows FROM "employees"', $sql);
        self::assertStringContainsString('NULLIF("employees"."dept_uuid", \'\')::uuid', $sql);
    }

    public function testCountWithConditions(): void
    {
        $stmt = new SelectStatement('employees');
        $stmt->addCondition(Condition::equals('status', 'active'));

        $sql = $stmt->count();

        self::assertStringContainsString('SELECT COUNT(*) as total_rows FROM "employees"', $sql);
        self::assertStringContainsString('WHERE (status = :w_0_status)', $sql);
    }

    // ── Limit & Offset ──

    public function testDefaultLimitAndOffset(): void
    {
        $stmt = new SelectStatement('employees');

        $sql = $stmt->getQuery();

        // By default, no LIMIT is applied (limit is null)
        self::assertStringNotContainsString('LIMIT', $sql);
        self::assertStringNotContainsString('OFFSET', $sql);
    }

    public function testCustomLimitAndOffset(): void
    {
        $stmt = new SelectStatement('employees');
        $stmt->setLimit(20);
        $stmt->setStart(40);

        $sql = $stmt->getQuery();

        self::assertStringContainsString('LIMIT 20', $sql);
        self::assertStringContainsString('OFFSET 40', $sql);
    }

    public function testAllRemovesLimitAndOffset(): void
    {
        $stmt = new SelectStatement('employees');
        $stmt->all();

        $sql = $stmt->getQuery();

        self::assertStringNotContainsString('LIMIT', $sql);
        self::assertStringNotContainsString('OFFSET', $sql);
    }

    // ── ORDER BY ──

    public function testOrderBy(): void
    {
        $stmt = new SelectStatement('employees');
        $stmt->addOrder('name', 'ASC');

        $sql = $stmt->getQuery();

        self::assertStringContainsString('ORDER BY name ASC', $sql);
    }

    // ── GROUP BY ──

    public function testGroupBy(): void
    {
        $stmt = new SelectStatement('employees', ['department', 'COUNT(*)']);
        $stmt->addGroupBy('department');
        $stmt->all();

        $sql = $stmt->getQuery();

        self::assertStringContainsString('GROUP BY department', $sql);
    }

    // ── Conditions ──

    public function testAddConditionAppearsInQuery(): void
    {
        $stmt = new SelectStatement('employees');
        $stmt->addCondition(Condition::equals('name', 'John'));

        $sql = $stmt->getQuery();

        self::assertStringContainsString('WHERE (name = :w_0_name)', $sql);
    }

    public function testGetWhereBindsReturnsConditionBinds(): void
    {
        $stmt = new SelectStatement('employees');
        $stmt->addCondition(Condition::equals('name', 'John'));

        $binds = $stmt->getWhereBinds();

        self::assertCount(1, $binds);
        self::assertSame('John', $binds[0]->value);
    }
}
