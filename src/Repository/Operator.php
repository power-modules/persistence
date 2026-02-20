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
    case NotNull = 'IS NOT NULL';
    case Like = 'LIKE';
    case NotLike = 'NOT LIKE';
    case Ilike = 'ILIKE';
    case NotIlike = 'NOT ILIKE';
    case Exists = 'EXISTS';

    // JSONB operators
    case JsonContains = '@>';
    case JsonContainedBy = '<@';
    case JsonHasKey = '?';
    case JsonHasAnyKey = '?|';
    case JsonHasAllKeys = '?&';

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
            self::JsonContains,
            self::JsonContainedBy,
            self::JsonHasKey => is_string($value),
            self::JsonHasAnyKey,
            self::JsonHasAllKeys => is_array($value) && array_is_list($value),
        };
    }
}
