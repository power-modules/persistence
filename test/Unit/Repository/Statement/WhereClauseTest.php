<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Repository\Statement;

use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Statement\WhereClause;
use PHPUnit\Framework\TestCase;

final class WhereClauseTest extends TestCase
{
    public function testEmptyWhereClause(): void
    {
        $where = new WhereClause();
        self::assertSame('', $where->toSql());
        self::assertEmpty($where->getBinds());
    }

    public function testSimpleCondition(): void
    {
        $where = new WhereClause();
        $where->add(Condition::equals('name', 'John'));

        self::assertSame(' WHERE (name = :w_0_name)', $where->toSql());
        $binds = $where->getBinds();
        self::assertCount(1, $binds);
        self::assertSame('John', $binds[0]->value);
    }

    public function testExistsCondition(): void
    {
        $where = new WhereClause();
        $where->add(Condition::exists('SELECT 1 FROM users WHERE id = 1'));

        self::assertSame(' WHERE (EXISTS (SELECT 1 FROM users WHERE id = 1))', $where->toSql());
        self::assertEmpty($where->getBinds());
    }

    public function testMultipleConditions(): void
    {
        $where = new WhereClause();
        $where->add(Condition::equals('name', 'John'));
        $where->add(Condition::exists('SELECT 1'));

        self::assertSame(' WHERE (name = :w_0_name) AND (EXISTS (SELECT 1))', $where->toSql());
        $binds = $where->getBinds();
        self::assertCount(1, $binds);
        self::assertSame('John', $binds[0]->value);
    }

    public function testInCondition(): void
    {
        $where = new WhereClause();
        $where->add(Condition::in('id', [1, 2, 3]));

        self::assertSame(' WHERE (id IN (:w_0_id,:w_1_id,:w_2_id))', $where->toSql());
        $binds = $where->getBinds();
        self::assertCount(3, $binds);
        self::assertSame(1, $binds[0]->value);
        self::assertSame(2, $binds[1]->value);
        self::assertSame(3, $binds[2]->value);
    }

    public function testIsNullCondition(): void
    {
        $where = new WhereClause();
        $where->add(Condition::isNull('deleted_at'));

        self::assertSame(' WHERE (deleted_at IS NULL)', $where->toSql());
        self::assertEmpty($where->getBinds());
    }
}
