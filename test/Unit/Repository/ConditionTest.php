<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Repository;

use InvalidArgumentException;
use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Operator;
use PHPUnit\Framework\TestCase;

final class ConditionTest extends TestCase
{
    public function testItValidatesValueAgainstOperator(): void
    {
        $condition = new Condition('col_a', Operator::Equals, 'valid_value');

        self::assertSame('col_a', $condition->column);
        self::assertSame(Operator::Equals, $condition->operator);
        self::assertSame('valid_value', $condition->value);
    }

    public function testItThrowsExceptionOnValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid operator: \'Equals\' cannot be used with the value of type \'NULL\'.');

        new Condition('col_a', Operator::Equals, null);
    }

    public function testExists(): void
    {
        $condition = Condition::exists('SELECT 1 FROM users WHERE id = 1');

        self::assertSame('', $condition->column);
        self::assertSame(Operator::Exists, $condition->operator);
        self::assertSame('SELECT 1 FROM users WHERE id = 1', $condition->value);
    }
}
