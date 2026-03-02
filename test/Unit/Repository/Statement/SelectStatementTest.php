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
    public function testJoinWithoutLocalKeyTypeProducesStandardSql(): void
    {
        $stmt = new SelectStatement('employees');
        $stmt->addJoin(new Join(JoinType::Inner, 'departments', 'dept_id', 'id'));

        $sql = $stmt->getQuery();

        self::assertStringContainsString(
            'INNER JOIN "departments" ON "departments"."id" = "employees"."dept_id"',
            $sql,
        );
    }

    public function testJoinWithLocalKeyTypeWrapsWithNullif(): void
    {
        $stmt = new SelectStatement('employees');
        $stmt->addJoin(new Join(JoinType::Inner, 'departments', 'dept_uuid', 'id', localKeyType: 'uuid'));

        $sql = $stmt->getQuery();

        self::assertStringContainsString(
            'INNER JOIN "departments" ON "departments"."id" = NULLIF("employees"."dept_uuid", \'\')::uuid',
            $sql,
        );
    }

    public function testJoinWithLocalKeyTypeAndAliasUsesAliasForForeignSide(): void
    {
        $stmt = new SelectStatement('employees');
        $stmt->addJoin(new Join(JoinType::Left, 'departments', 'dept_uuid', 'id', alias: 'd', localKeyType: 'uuid'));

        $sql = $stmt->getQuery();

        self::assertStringContainsString(
            'LEFT JOIN "departments" "d" ON "d"."id" = NULLIF("employees"."dept_uuid", \'\')::uuid',
            $sql,
        );
    }

    public function testJoinWithLocalKeyTypeAndCustomLocalTable(): void
    {
        $stmt = new SelectStatement('employees');
        $stmt->addJoin(new Join(JoinType::Inner, 'departments', 'dept_uuid', 'id', localTable: 'contracts', localKeyType: 'uuid'));

        $sql = $stmt->getQuery();

        self::assertStringContainsString(
            'INNER JOIN "departments" ON "departments"."id" = NULLIF("contracts"."dept_uuid", \'\')::uuid',
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

    public function testCountQueryWithLocalKeyTypeJoinWrapsWithNullif(): void
    {
        $stmt = new SelectStatement('employees');
        $stmt->addJoin(new Join(JoinType::Inner, 'departments', 'dept_uuid', 'id', localKeyType: 'uuid'));

        $sql = $stmt->count();

        self::assertStringContainsString('SELECT COUNT(*) as total_rows FROM "employees"', $sql);
        self::assertStringContainsString(
            'INNER JOIN "departments" ON "departments"."id" = NULLIF("employees"."dept_uuid", \'\')::uuid',
            $sql,
        );
    }

    public function testJoinWithAliasWithoutLocalKeyTypeProducesStandardSql(): void
    {
        $stmt = new SelectStatement('employees');
        $stmt->addJoin(new Join(JoinType::Inner, 'departments', 'dept_id', 'id', alias: 'd'));

        $sql = $stmt->getQuery();

        self::assertStringContainsString(
            'INNER JOIN "departments" "d" ON "d"."id" = "employees"."dept_id"',
            $sql,
        );
    }

    public function testJoinWithLocalKeyTypeIntegerCast(): void
    {
        $stmt = new SelectStatement('orders');
        $stmt->addJoin(new Join(JoinType::Left, 'products', 'product_ext_id', 'id', localKeyType: 'integer'));

        $sql = $stmt->getQuery();

        self::assertStringContainsString(
            'LEFT JOIN "products" ON "products"."id" = NULLIF("orders"."product_ext_id", \'\')::integer',
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
            'INNER JOIN "departments" ON "departments"."id" = NULLIF("employees"."dept_uuid", \'\')::uuid',
            $sql,
        );
    }
}
