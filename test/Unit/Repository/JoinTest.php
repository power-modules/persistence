<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Repository;

use Modular\Persistence\Repository\Join;
use Modular\Persistence\Repository\JoinType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Join::class)]
final class JoinTest extends TestCase
{
    public function testBasicInnerJoin(): void
    {
        $join = new Join(JoinType::Inner, 'departments', 'dept_id', 'id');

        self::assertSame(
            'INNER JOIN "departments" ON "departments"."id" = "employees"."dept_id"',
            $join->toSql('employees'),
        );
    }

    public function testLeftJoin(): void
    {
        $join = new Join(JoinType::Left, 'orders', 'user_id', 'customer_id');

        self::assertSame(
            'LEFT JOIN "orders" ON "orders"."customer_id" = "users"."user_id"',
            $join->toSql('users'),
        );
    }

    public function testOuterJoin(): void
    {
        $join = new Join(JoinType::Outer, 'orders', 'user_id', 'customer_id');

        self::assertSame(
            'OUTER JOIN "orders" ON "orders"."customer_id" = "users"."user_id"',
            $join->toSql('users'),
        );
    }

    public function testJoinWithAlias(): void
    {
        $join = new Join(JoinType::Inner, 'departments', 'dept_id', 'id', alias: 'd');

        self::assertSame(
            'INNER JOIN "departments" "d" ON "d"."id" = "employees"."dept_id"',
            $join->toSql('employees'),
        );
    }

    public function testJoinWithCustomLocalTable(): void
    {
        $join = new Join(JoinType::Inner, 'departments', 'dept_id', 'id', localTable: 'contracts');

        self::assertSame(
            'INNER JOIN "departments" ON "departments"."id" = "contracts"."dept_id"',
            $join->toSql('employees'),
        );
    }

    public function testLocalKeyTypeWrapsWithNullif(): void
    {
        $join = new Join(JoinType::Inner, 'departments', 'dept_uuid', 'id', localKeyType: 'uuid');

        self::assertSame(
            'INNER JOIN "departments" ON "departments"."id" = NULLIF("employees"."dept_uuid", \'\')::uuid',
            $join->toSql('employees'),
        );
    }

    public function testLocalKeyTypeIntegerCast(): void
    {
        $join = new Join(JoinType::Left, 'products', 'product_ext_id', 'id', localKeyType: 'integer');

        self::assertSame(
            'LEFT JOIN "products" ON "products"."id" = NULLIF("orders"."product_ext_id", \'\')::integer',
            $join->toSql('orders'),
        );
    }

    public function testLocalKeyTypeWithAlias(): void
    {
        $join = new Join(JoinType::Left, 'departments', 'dept_uuid', 'id', alias: 'd', localKeyType: 'uuid');

        self::assertSame(
            'LEFT JOIN "departments" "d" ON "d"."id" = NULLIF("employees"."dept_uuid", \'\')::uuid',
            $join->toSql('employees'),
        );
    }

    public function testLocalKeyTypeWithCustomLocalTable(): void
    {
        $join = new Join(JoinType::Inner, 'departments', 'dept_uuid', 'id', localTable: 'contracts', localKeyType: 'uuid');

        self::assertSame(
            'INNER JOIN "departments" ON "departments"."id" = NULLIF("contracts"."dept_uuid", \'\')::uuid',
            $join->toSql('employees'),
        );
    }

    public function testLocalKeyTypeWithAliasAndCustomLocalTable(): void
    {
        $join = new Join(JoinType::Left, 'departments', 'dept_uuid', 'id', localTable: 'contracts', alias: 'd', localKeyType: 'uuid');

        self::assertSame(
            'LEFT JOIN "departments" "d" ON "d"."id" = NULLIF("contracts"."dept_uuid", \'\')::uuid',
            $join->toSql('employees'),
        );
    }
}
