<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Unit\Repository;

use Iterator;
use Modular\Persistence\Repository\Operator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Operator::class)]
final class OperatorTest extends TestCase
{
    // ── Data Providers ──

    public static function scalarValuesProvider(): Iterator
    {
        yield 'int min' => [PHP_INT_MIN];
        yield 'int max' => [PHP_INT_MAX];
        yield 'negative float' => [-1.99];
        yield 'positive float' => [1.99];
        yield 'scientific notation' => [1.2e3];
        yield 'zero' => [0];
        yield 'string' => ['string'];
        yield 'true' => [true];
        yield 'false' => [false];
    }

    public static function arrayValuesProvider(): Iterator
    {
        yield 'empty array' => [[]];
        yield 'single element' => [[1]];
        yield 'multiple elements' => [[-1, 0, 1]];
    }

    // ── Standard Operators ──

    #[DataProvider('scalarValuesProvider')]
    public function testOperatorsAgainstScalarValues(mixed $value): void
    {
        self::assertTrue(Operator::Equals->validate($value), Operator::Equals->name . ' failed.');
        self::assertTrue(Operator::NotEquals->validate($value), Operator::NotEquals->name . ' failed.');
        self::assertTrue(Operator::Greater->validate($value), Operator::Greater->name . ' failed.');
        self::assertTrue(Operator::GreaterEquals->validate($value), Operator::GreaterEquals->name . ' failed.');
        self::assertTrue(Operator::Less->validate($value), Operator::Less->name . ' failed.');
        self::assertTrue(Operator::LessEquals->validate($value), Operator::LessEquals->name . ' failed.');
        self::assertTrue(Operator::Like->validate($value), Operator::Like->name . ' failed.');
        self::assertFalse(Operator::In->validate($value), Operator::In->name . ' failed.');
        self::assertFalse(Operator::NotIn->validate($value), Operator::NotIn->name . ' failed.');
        self::assertFalse(Operator::IsNull->validate($value), Operator::IsNull->name . ' failed.');
        self::assertFalse(Operator::NotNull->validate($value), Operator::NotNull->name . ' failed.');
    }

    #[DataProvider('arrayValuesProvider')]
    public function testOperatorsAgainstArrays(mixed $value): void
    {
        self::assertFalse(Operator::Equals->validate($value), Operator::Equals->name . ' failed.');
        self::assertFalse(Operator::NotEquals->validate($value), Operator::NotEquals->name . ' failed.');
        self::assertFalse(Operator::Greater->validate($value), Operator::Greater->name . ' failed.');
        self::assertFalse(Operator::GreaterEquals->validate($value), Operator::GreaterEquals->name . ' failed.');
        self::assertFalse(Operator::Less->validate($value), Operator::Less->name . ' failed.');
        self::assertFalse(Operator::LessEquals->validate($value), Operator::LessEquals->name . ' failed.');
        self::assertFalse(Operator::Like->validate($value), Operator::Like->name . ' failed.');
        self::assertTrue(Operator::In->validate($value), Operator::In->name . ' failed.');
        self::assertTrue(Operator::NotIn->validate($value), Operator::NotIn->name . ' failed.');
        self::assertFalse(Operator::IsNull->validate($value), Operator::IsNull->name . ' failed.');
        self::assertFalse(Operator::NotNull->validate($value), Operator::NotNull->name . ' failed.');
    }

    public function testOperatorsAgainstNull(): void
    {
        $value = null;

        self::assertFalse(Operator::Equals->validate($value));
        self::assertFalse(Operator::NotEquals->validate($value));
        self::assertFalse(Operator::Greater->validate($value));
        self::assertFalse(Operator::GreaterEquals->validate($value));
        self::assertFalse(Operator::Less->validate($value));
        self::assertFalse(Operator::LessEquals->validate($value));
        self::assertFalse(Operator::Like->validate($value));
        self::assertFalse(Operator::In->validate($value));
        self::assertFalse(Operator::NotIn->validate($value));
        self::assertTrue(Operator::IsNull->validate($value));
        self::assertTrue(Operator::NotNull->validate($value));
    }

    // ── Exists Operator ──

    public function testExistsAcceptsStrings(): void
    {
        self::assertTrue(Operator::Exists->validate('SELECT 1'));
    }

    public function testExistsRejectsNonStrings(): void
    {
        self::assertFalse(Operator::Exists->validate(1));
        self::assertFalse(Operator::Exists->validate(null));
        self::assertFalse(Operator::Exists->validate([]));
    }

    // ── JSONB Operators ──

    public function testJsonContainsAcceptsString(): void
    {
        self::assertTrue(Operator::JsonContains->validate('{"status":"active"}'));
    }

    public function testJsonContainsRejectsNonString(): void
    {
        self::assertFalse(Operator::JsonContains->validate(42));
        self::assertFalse(Operator::JsonContains->validate(null));
        self::assertFalse(Operator::JsonContains->validate([]));
        self::assertFalse(Operator::JsonContains->validate(true));
    }

    public function testJsonContainedByAcceptsString(): void
    {
        self::assertTrue(Operator::JsonContainedBy->validate('{"a":1}'));
    }

    public function testJsonContainedByRejectsNonString(): void
    {
        self::assertFalse(Operator::JsonContainedBy->validate(42));
        self::assertFalse(Operator::JsonContainedBy->validate(null));
    }

    public function testJsonHasKeyAcceptsString(): void
    {
        self::assertTrue(Operator::JsonHasKey->validate('status'));
    }

    public function testJsonHasKeyRejectsNonString(): void
    {
        self::assertFalse(Operator::JsonHasKey->validate(42));
        self::assertFalse(Operator::JsonHasKey->validate(null));
        self::assertFalse(Operator::JsonHasKey->validate(['a']));
    }

    public function testJsonHasAnyKeyAcceptsListArray(): void
    {
        self::assertTrue(Operator::JsonHasAnyKey->validate(['status', 'lang']));
    }

    public function testJsonHasAnyKeyRejectsAssociativeArray(): void
    {
        self::assertFalse(Operator::JsonHasAnyKey->validate(['key' => 'val']));
    }

    public function testJsonHasAnyKeyRejectsNonArray(): void
    {
        self::assertFalse(Operator::JsonHasAnyKey->validate('status'));
        self::assertFalse(Operator::JsonHasAnyKey->validate(null));
    }

    public function testJsonHasAllKeysAcceptsListArray(): void
    {
        self::assertTrue(Operator::JsonHasAllKeys->validate(['status', 'lang']));
    }

    public function testJsonHasAllKeysRejectsAssociativeArray(): void
    {
        self::assertFalse(Operator::JsonHasAllKeys->validate(['key' => 'val']));
    }

    public function testJsonHasAllKeysRejectsNonArray(): void
    {
        self::assertFalse(Operator::JsonHasAllKeys->validate('status'));
        self::assertFalse(Operator::JsonHasAllKeys->validate(null));
    }
}
