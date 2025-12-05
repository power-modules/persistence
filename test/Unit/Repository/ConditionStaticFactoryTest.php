<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Repository;

use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Operator;
use Modular\Persistence\Test\Unit\Repository\Fixture\Schema;
use PHPUnit\Framework\TestCase;

final class ConditionStaticFactoryTest extends TestCase
{
    public function testEqualsWithEnum(): void
    {
        $condition = Condition::equals(Schema::Name, 'test');
        self::assertSame('name', $condition->column);
        self::assertSame(Operator::Equals, $condition->operator);
        self::assertSame('test', $condition->value);
    }

    public function testEqualsWithStringAlias(): void
    {
        $condition = Condition::equals('u.name', 'test');
        self::assertSame('u.name', $condition->column);
        self::assertSame(Operator::Equals, $condition->operator);
        self::assertSame('test', $condition->value);
    }

    public function testNotEquals(): void
    {
        $condition = Condition::notEquals('u.age', 18);
        self::assertSame('u.age', $condition->column);
        self::assertSame(Operator::NotEquals, $condition->operator);
        self::assertSame(18, $condition->value);
    }

    public function testGreater(): void
    {
        $condition = Condition::greater('u.age', 18);
        self::assertSame('u.age', $condition->column);
        self::assertSame(Operator::Greater, $condition->operator);
    }

    public function testGreaterEquals(): void
    {
        $condition = Condition::greaterEquals('u.age', 18);
        self::assertSame('u.age', $condition->column);
        self::assertSame(Operator::GreaterEquals, $condition->operator);
    }

    public function testExists(): void
    {
        $condition = Condition::exists('SELECT 1');
        self::assertSame('', $condition->column);
        self::assertSame(Operator::Exists, $condition->operator);
        self::assertSame('SELECT 1', $condition->value);
    }

    public function testLess(): void
    {
        $condition = Condition::less('u.age', 18);
        self::assertSame('u.age', $condition->column);
        self::assertSame(Operator::Less, $condition->operator);
    }

    public function testLessEquals(): void
    {
        $condition = Condition::lessEquals('u.age', 18);
        self::assertSame('u.age', $condition->column);
        self::assertSame(Operator::LessEquals, $condition->operator);
    }

    public function testIn(): void
    {
        $condition = Condition::in('u.status', ['active', 'pending']);
        self::assertSame('u.status', $condition->column);
        self::assertSame(Operator::In, $condition->operator);
        self::assertSame(['active', 'pending'], $condition->value);
    }

    public function testNotIn(): void
    {
        $condition = Condition::notIn('u.status', ['banned']);
        self::assertSame('u.status', $condition->column);
        self::assertSame(Operator::NotIn, $condition->operator);
    }

    public function testIsNull(): void
    {
        $condition = Condition::isNull('u.deleted_at');
        self::assertSame('u.deleted_at', $condition->column);
        self::assertSame(Operator::IsNull, $condition->operator);
        self::assertNull($condition->value);
    }

    public function testNotNull(): void
    {
        $condition = Condition::notNull('u.created_at');
        self::assertSame('u.created_at', $condition->column);
        self::assertSame(Operator::NotNull, $condition->operator);
        self::assertNull($condition->value);
    }

    public function testLike(): void
    {
        $condition = Condition::like('u.name', '%john%');
        self::assertSame('u.name', $condition->column);
        self::assertSame(Operator::Like, $condition->operator);
    }

    public function testNotLike(): void
    {
        $condition = Condition::notLike('u.name', '%doe%');
        self::assertSame('u.name', $condition->column);
        self::assertSame(Operator::NotLike, $condition->operator);
    }

    public function testIlike(): void
    {
        $condition = Condition::ilike('u.name', '%john%');
        self::assertSame('u.name', $condition->column);
        self::assertSame(Operator::Ilike, $condition->operator);
    }

    public function testNotIlike(): void
    {
        $condition = Condition::notIlike('u.name', '%doe%');
        self::assertSame('u.name', $condition->column);
        self::assertSame(Operator::NotIlike, $condition->operator);
    }
}
