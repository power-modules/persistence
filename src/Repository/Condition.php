<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository;

use BackedEnum;
use InvalidArgumentException;

final class Condition
{
    public private(set) string $column {
        set(BackedEnum|string $value) => $value instanceof BackedEnum ? (string) $value->value : $value;
    }

    public function __construct(
        BackedEnum|string $column,
        public readonly Operator $operator,
        public readonly mixed $value,
        public readonly ConditionXor $xor = ConditionXor::And,
    ) {
        $this->column = $column;

        if ($operator->validate($value) === false) {
            throw new InvalidArgumentException(
                sprintf('Invalid operator: \'%s\' cannot be used with the value of type \'%s\'.', $operator->name, gettype($value)),
            );
        }
    }

    public static function equals(BackedEnum $column, mixed $value): self
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return new self($column, Operator::Equals, $value);
    }

    public static function notEquals(BackedEnum $column, mixed $value): self
    {
        return new self($column, Operator::NotEquals, $value);
    }

    public static function greater(BackedEnum $column, mixed $value): self
    {
        return new self($column, Operator::Greater, $value);
    }

    public static function greaterEquals(BackedEnum $column, mixed $value): self
    {
        return new self($column, Operator::GreaterEquals, $value);
    }

    public static function less(BackedEnum $column, mixed $value): self
    {
        return new self($column, Operator::Less, $value);
    }

    public static function lessEquals(BackedEnum $column, mixed $value): self
    {
        return new self($column, Operator::LessEquals, $value);
    }

    public static function in(BackedEnum $column, mixed $value): self
    {
        return new self($column, Operator::In, $value);
    }

    public static function notIn(BackedEnum $column, mixed $value): self
    {
        return new self($column, Operator::NotIn, $value);
    }

    public static function isNull(BackedEnum $column): self
    {
        return new self($column, Operator::IsNull, null);
    }

    public static function notNull(BackedEnum $column): self
    {
        return new self($column, Operator::NotNull, null);
    }

    public static function like(BackedEnum $column, mixed $value): self
    {
        return new self($column, Operator::Like, $value);
    }

    public static function notLike(BackedEnum $column, mixed $value): self
    {
        return new self($column, Operator::NotLike, $value);
    }

    public static function ilike(BackedEnum $column, mixed $value): self
    {
        return new self($column, Operator::Ilike, $value);
    }

    public static function notIlike(BackedEnum $column, mixed $value): self
    {
        return new self($column, Operator::NotIlike, $value);
    }
}
