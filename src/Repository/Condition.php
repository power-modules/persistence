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

    public static function equals(BackedEnum|string $column, mixed $value): self
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return new self($column, Operator::Equals, $value);
    }

    public static function notEquals(BackedEnum|string $column, mixed $value): self
    {
        return new self($column, Operator::NotEquals, $value);
    }

    public static function greater(BackedEnum|string $column, mixed $value): self
    {
        return new self($column, Operator::Greater, $value);
    }

    public static function greaterEquals(BackedEnum|string $column, mixed $value): self
    {
        return new self($column, Operator::GreaterEquals, $value);
    }

    public static function less(BackedEnum|string $column, mixed $value): self
    {
        return new self($column, Operator::Less, $value);
    }

    public static function lessEquals(BackedEnum|string $column, mixed $value): self
    {
        return new self($column, Operator::LessEquals, $value);
    }

    public static function in(BackedEnum|string $column, mixed $value): self
    {
        return new self($column, Operator::In, $value);
    }

    public static function notIn(BackedEnum|string $column, mixed $value): self
    {
        return new self($column, Operator::NotIn, $value);
    }

    public static function isNull(BackedEnum|string $column): self
    {
        return new self($column, Operator::IsNull, null);
    }

    public static function notNull(BackedEnum|string $column): self
    {
        return new self($column, Operator::NotNull, null);
    }

    public static function like(BackedEnum|string $column, mixed $value): self
    {
        return new self($column, Operator::Like, $value);
    }

    public static function notLike(BackedEnum|string $column, mixed $value): self
    {
        return new self($column, Operator::NotLike, $value);
    }

    public static function ilike(BackedEnum|string $column, mixed $value): self
    {
        return new self($column, Operator::Ilike, $value);
    }

    public static function notIlike(BackedEnum|string $column, mixed $value): self
    {
        return new self($column, Operator::NotIlike, $value);
    }

    public static function exists(string $subquery): self
    {
        return new self('', Operator::Exists, $subquery);
    }

    /**
     * JSONB containment: column @> value::jsonb
     *
     * @param string $jsonValue Pre-encoded JSON string
     */
    public static function jsonContains(BackedEnum|string $column, string $jsonValue): self
    {
        return new self($column, Operator::JsonContains, $jsonValue);
    }

    /**
     * Reverse JSONB containment: column <@ value::jsonb
     *
     * @param string $jsonValue Pre-encoded JSON string
     */
    public static function jsonContainedBy(BackedEnum|string $column, string $jsonValue): self
    {
        return new self($column, Operator::JsonContainedBy, $jsonValue);
    }

    /**
     * JSONB key existence: column ? key
     */
    public static function jsonHasKey(BackedEnum|string $column, string $key): self
    {
        return new self($column, Operator::JsonHasKey, $key);
    }

    /**
     * JSONB any key existence: column ?| array['key1','key2']
     *
     * @param array<string> $keys
     */
    public static function jsonHasAnyKey(BackedEnum|string $column, array $keys): self
    {
        return new self($column, Operator::JsonHasAnyKey, $keys);
    }

    /**
     * JSONB all keys existence: column ?& array['key1','key2']
     *
     * @param array<string> $keys
     */
    public static function jsonHasAllKeys(BackedEnum|string $column, array $keys): self
    {
        return new self($column, Operator::JsonHasAllKeys, $keys);
    }

    /**
     * Use a raw SQL expression as the column (e.g. JSONB path extraction).
     *
     * The expression is NOT quoted as an identifier — it is used verbatim.
     * Example: Condition::jsonPath('"metadata"->>\'{$key}\'', Operator::Equals, 'active')
     */
    public static function jsonPath(string $expression, Operator $operator, mixed $value): self
    {
        return new self($expression, $operator, $value);
    }
}
