<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Unit\Repository;

use InvalidArgumentException;
use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\ConditionXor;
use Modular\Persistence\Repository\Operator;
use Modular\Persistence\Tests\Unit\Fixture\EmployeeSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests Condition construction, validation, and all static factories.
 *
 * Merged from the old ConditionTest + ConditionStaticFactoryTest to eliminate
 * splitting of closely related behaviours across two classes.
 */
#[CoversClass(Condition::class)]
final class ConditionTest extends TestCase
{
    // ── Construction & Validation ──

    public function testConstructorValidatesValueAgainstOperator(): void
    {
        $condition = new Condition('col_a', Operator::Equals, 'valid_value');

        self::assertSame('col_a', $condition->column);
        self::assertSame(Operator::Equals, $condition->operator);
        self::assertSame('valid_value', $condition->value);
    }

    public function testConstructorThrowsOnInvalidValueForOperator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid operator: \'Equals\' cannot be used with the value of type \'NULL\'.');

        new Condition('col_a', Operator::Equals, null);
    }

    // ── Static Factories: Standard Operators ──

    public function testEqualsWithBackedEnum(): void
    {
        $c = Condition::equals(EmployeeSchema::Name, 'test');
        self::assertSame('name', $c->column);
        self::assertSame(Operator::Equals, $c->operator);
        self::assertSame('test', $c->value);
    }

    public function testEqualsWithStringColumn(): void
    {
        $c = Condition::equals('u.name', 'test');
        self::assertSame('u.name', $c->column);
        self::assertSame(Operator::Equals, $c->operator);
    }

    public function testNotEquals(): void
    {
        $c = Condition::notEquals('age', 18);
        self::assertSame(Operator::NotEquals, $c->operator);
        self::assertSame(18, $c->value);
    }

    public function testGreater(): void
    {
        $c = Condition::greater('age', 18);
        self::assertSame(Operator::Greater, $c->operator);
    }

    public function testGreaterEquals(): void
    {
        $c = Condition::greaterEquals('age', 18);
        self::assertSame(Operator::GreaterEquals, $c->operator);
    }

    public function testLess(): void
    {
        $c = Condition::less('age', 18);
        self::assertSame(Operator::Less, $c->operator);
    }

    public function testLessEquals(): void
    {
        $c = Condition::lessEquals('age', 18);
        self::assertSame(Operator::LessEquals, $c->operator);
    }

    public function testIn(): void
    {
        $c = Condition::in('status', ['active', 'pending']);
        self::assertSame(Operator::In, $c->operator);
        self::assertSame(['active', 'pending'], $c->value);
    }

    public function testNotIn(): void
    {
        $c = Condition::notIn('status', ['banned']);
        self::assertSame(Operator::NotIn, $c->operator);
    }

    public function testIsNull(): void
    {
        $c = Condition::isNull('deleted_at');
        self::assertSame(Operator::IsNull, $c->operator);
        self::assertNull($c->value);
    }

    public function testNotNull(): void
    {
        $c = Condition::notNull('created_at');
        self::assertSame(Operator::NotNull, $c->operator);
        self::assertNull($c->value);
    }

    public function testLike(): void
    {
        $c = Condition::like('name', '%john%');
        self::assertSame(Operator::Like, $c->operator);
    }

    public function testNotLike(): void
    {
        $c = Condition::notLike('name', '%doe%');
        self::assertSame(Operator::NotLike, $c->operator);
    }

    public function testIlike(): void
    {
        $c = Condition::ilike('name', '%john%');
        self::assertSame(Operator::Ilike, $c->operator);
    }

    public function testNotIlike(): void
    {
        $c = Condition::notIlike('name', '%doe%');
        self::assertSame(Operator::NotIlike, $c->operator);
    }

    public function testExists(): void
    {
        $c = Condition::exists('SELECT 1 FROM users WHERE id = 1');
        self::assertSame('', $c->column);
        self::assertSame(Operator::Exists, $c->operator);
        self::assertSame('SELECT 1 FROM users WHERE id = 1', $c->value);
    }

    // ── Static Factories: JSONB Operators ──

    public function testJsonContains(): void
    {
        $c = Condition::jsonContains('metadata', '{"status":"active"}');
        self::assertSame(Operator::JsonContains, $c->operator);
        self::assertSame('{"status":"active"}', $c->value);
    }

    public function testJsonContainsWithEnum(): void
    {
        $c = Condition::jsonContains(EmployeeSchema::Name, '{"x":1}');
        self::assertSame('name', $c->column);
        self::assertSame(Operator::JsonContains, $c->operator);
    }

    public function testJsonContainedBy(): void
    {
        $c = Condition::jsonContainedBy('metadata', '{"status":"active","lang":"en"}');
        self::assertSame(Operator::JsonContainedBy, $c->operator);
        self::assertSame('{"status":"active","lang":"en"}', $c->value);
    }

    public function testJsonHasKey(): void
    {
        $c = Condition::jsonHasKey('metadata', 'status');
        self::assertSame(Operator::JsonHasKey, $c->operator);
        self::assertSame('status', $c->value);
    }

    public function testJsonHasAnyKey(): void
    {
        $c = Condition::jsonHasAnyKey('metadata', ['status', 'lang']);
        self::assertSame(Operator::JsonHasAnyKey, $c->operator);
        self::assertSame(['status', 'lang'], $c->value);
    }

    public function testJsonHasAllKeys(): void
    {
        $c = Condition::jsonHasAllKeys('metadata', ['status', 'lang']);
        self::assertSame(Operator::JsonHasAllKeys, $c->operator);
        self::assertSame(['status', 'lang'], $c->value);
    }

    public function testJsonPath(): void
    {
        $c = Condition::jsonPath('"metadata"->>\'status\'', Operator::Equals, 'active');
        self::assertSame('"metadata"->>\'status\'', $c->column);
        self::assertSame(Operator::Equals, $c->operator);
        self::assertSame('active', $c->value);
    }

    public function testJsonPathWithIlike(): void
    {
        $c = Condition::jsonPath('"metadata"->>\'title\'', Operator::Ilike, 'test');
        self::assertSame(Operator::Ilike, $c->operator);
    }

    public function testJsonPathDefaultXorIsAnd(): void
    {
        $c = Condition::jsonPath('"data"->>\'key\'', Operator::Equals, 'val');
        self::assertSame(ConditionXor::And, $c->xor);
    }
}
