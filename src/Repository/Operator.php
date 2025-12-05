<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository;

enum Operator: string
{
    case Equals = '=';
    case NotEquals = '!=';
    case Greater = '>';
    case GreaterEquals = '>=';
    case Less = '<';
    case LessEquals = '<=';
    case In = 'IN';
    case NotIn = 'NOT IN';
    case IsNull = 'IS NULL';
    case NotNull = 'NOT NULL';
    case Like = 'LIKE';
    case NotLike = 'NOT LIKE';
    case Ilike = 'ILIKE';
    case NotIlike = 'NOT ILIKE';
    case Exists = 'EXISTS';

    public function validate(mixed $value): bool
    {
        return match ($this) {
            self::Equals,
            self::NotEquals,
            self::Greater,
            self::GreaterEquals,
            self::Less,
            self::LessEquals,
            self::Like,
            self::NotLike,
            self::Ilike,
            self::NotIlike => is_scalar($value) || $value instanceof \DateTimeInterface,
            self::In,
            self::NotIn => is_array($value),
            self::IsNull,
            self::NotNull => $value === null,
            self::Exists => is_string($value),
        };
    }
}
