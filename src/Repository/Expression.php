<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository;

use Stringable;

/**
 * A raw SQL expression used as a condition operand without parameter binding.
 *
 * Use this when the right-hand side of a condition is another column or a SQL
 * expression rather than a bound value — for example, a correlated subquery
 * predicate such as `child.parent_id = "parent"."id"`.
 *
 * The expression is emitted verbatim and is NEVER quoted or bound. Only pass
 * trusted, internally-constructed SQL — never user input.
 */
final readonly class Expression implements Stringable
{
    public function __construct(
        public string $sql,
    ) {
    }

    public function __toString(): string
    {
        return $this->sql;
    }
}
