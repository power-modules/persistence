<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Repository\Statement;

use Modular\Persistence\Repository\Join;
use Modular\Persistence\Repository\JoinType;
use Modular\Persistence\Repository\Statement\SelectStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SelectStatement::class)]
final class SelectStatementTest extends TestCase
{
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

    public function testCountQueryIncludesJoinWithLocalKeyType(): void
    {
        $stmt = new SelectStatement('employees');
        $stmt->addJoin(new Join(JoinType::Inner, 'departments', 'dept_uuid', 'id', localKeyType: 'uuid'));

        $sql = $stmt->count();

        self::assertStringContainsString('SELECT COUNT(*) as total_rows FROM "employees"', $sql);
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

    public function testJoinWithNamespace(): void
    {
        $stmt = new SelectStatement('employees', ['*'], 'my_schema');
        $stmt->addJoin(new Join(JoinType::Inner, 'departments', 'dept_uuid', 'id', localKeyType: 'uuid'));

        $sql = $stmt->getQuery();

        self::assertStringContainsString('SELECT * FROM "my_schema"."employees"', $sql);
        self::assertStringContainsString(
            'NULLIF("employees"."dept_uuid", \'\')::uuid',
            $sql,
        );
    }
}
