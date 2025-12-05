<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Repository;

use Iterator;
use Modular\Persistence\Repository\Operator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OperatorTest extends TestCase
{
    public static function scalarValuesProvider(): Iterator
    {
        yield [PHP_INT_MIN];
        yield [PHP_INT_MAX];
        yield [-1.99];
        yield [1.99];
        yield [1.2e3];
        yield [0];
        yield ['string'];
        yield [true];
        yield [false];
    }

    #[DataProvider('scalarValuesProvider')]
    public function testOperatorsAgainstScalarValues(mixed $value): void
    {
        self::assertTrue(Operator::Equals->validate($value), Operator::Equals->name . ' operator has failed.');
        self::assertTrue(Operator::NotEquals->validate($value), Operator::NotEquals->name . ' operator has failed.');
        self::assertTrue(Operator::Greater->validate($value), Operator::Greater->name . ' operator has failed.');
        self::assertTrue(Operator::GreaterEquals->validate($value), Operator::GreaterEquals->name . ' operator has failed.');
        self::assertTrue(Operator::Less->validate($value), Operator::Less->name . ' operator has failed.');
        self::assertTrue(Operator::LessEquals->validate($value), Operator::LessEquals->name . ' operator has failed.');
        self::assertTrue(Operator::Like->validate($value), Operator::Like->name . ' operator has failed.');
        self::assertFalse(Operator::In->validate($value), Operator::In->name . ' operator has failed.');
        self::assertFalse(Operator::NotIn->validate($value), Operator::NotIn->name . ' operator has failed.');
        self::assertFalse(Operator::IsNull->validate($value), Operator::IsNull->name . ' operator has failed.');
        self::assertFalse(Operator::NotNull->validate($value), Operator::NotNull->name . ' operator has failed.');
    }

    public static function arrayValuesProvider(): Iterator
    {
        yield [[]];
        yield [[1]];
        yield [[-1, 0, 1]];
    }

    #[DataProvider('arrayValuesProvider')]
    public function testOperatorsAgainstArrays(mixed $value): void
    {
        self::assertFalse(Operator::Equals->validate($value), Operator::Equals->name . ' operator has failed.');
        self::assertFalse(Operator::NotEquals->validate($value), Operator::NotEquals->name . ' operator has failed.');
        self::assertFalse(Operator::Greater->validate($value), Operator::Greater->name . ' operator has failed.');
        self::assertFalse(Operator::GreaterEquals->validate($value), Operator::GreaterEquals->name . ' operator has failed.');
        self::assertFalse(Operator::Less->validate($value), Operator::Less->name . ' operator has failed.');
        self::assertFalse(Operator::LessEquals->validate($value), Operator::LessEquals->name . ' operator has failed.');
        self::assertFalse(Operator::Like->validate($value), Operator::Like->name . ' operator has failed.');
        self::assertTrue(Operator::In->validate($value), Operator::In->name . ' operator has failed.');
        self::assertTrue(Operator::NotIn->validate($value), Operator::NotIn->name . ' operator has failed.');
        self::assertFalse(Operator::IsNull->validate($value), Operator::IsNull->name . ' operator has failed.');
        self::assertFalse(Operator::NotNull->validate($value), Operator::NotNull->name . ' operator has failed.');
    }

    public function testOperatorsAgainstNull(): void
    {
        $value = null;

        self::assertFalse(Operator::Equals->validate($value), Operator::Equals->name . ' operator has failed.');
        self::assertFalse(Operator::NotEquals->validate($value), Operator::NotEquals->name . ' operator has failed.');
        self::assertFalse(Operator::Greater->validate($value), Operator::Greater->name . ' operator has failed.');
        self::assertFalse(Operator::GreaterEquals->validate($value), Operator::GreaterEquals->name . ' operator has failed.');
        self::assertFalse(Operator::Less->validate($value), Operator::Less->name . ' operator has failed.');
        self::assertFalse(Operator::LessEquals->validate($value), Operator::LessEquals->name . ' operator has failed.');
        self::assertFalse(Operator::Like->validate($value), Operator::Like->name . ' operator has failed.');
        self::assertFalse(Operator::In->validate($value), Operator::In->name . ' operator has failed.');
        self::assertFalse(Operator::NotIn->validate($value), Operator::NotIn->name . ' operator has failed.');
        self::assertTrue(Operator::IsNull->validate($value), Operator::IsNull->name . ' operator has failed.');
        self::assertTrue(Operator::NotNull->validate($value), Operator::NotNull->name . ' operator has failed.');
    }

    public function testExistsOperator(): void
    {
        self::assertTrue(Operator::Exists->validate('SELECT 1'), 'Exists operator should accept strings.');
        self::assertFalse(Operator::Exists->validate(1), 'Exists operator should not accept integers.');
        self::assertFalse(Operator::Exists->validate(null), 'Exists operator should not accept null.');
        self::assertFalse(Operator::Exists->validate([]), 'Exists operator should not accept arrays.');
    }
}
