<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository;

/**
 * A parenthesized group of conditions (and/or nested groups) joined by a single
 * boolean connective.
 *
 * Groups can be nested arbitrarily, which lets you express boolean trees that a
 * flat WHERE list cannot — e.g. `(a AND b) OR (c AND d)`:
 *
 *     ConditionGroup::any(
 *         ConditionGroup::all($a, $b),
 *         ConditionGroup::all($c, $d),
 *     );
 *
 * Members are always joined with the group's own {@see ConditionGroup::$connective};
 * the per-{@see Condition} `xor` is not used inside a group.
 */
final class ConditionGroup
{
    /**
     * @var list<Condition|ConditionGroup>
     */
    public private(set) array $members;

    public function __construct(
        public readonly ConditionXor $connective = ConditionXor::And,
        Condition|ConditionGroup ...$members,
    ) {
        $this->members = array_values($members);
    }

    /**
     * Create a group whose members are joined with AND.
     */
    public static function all(Condition|ConditionGroup ...$members): self
    {
        return new self(ConditionXor::And, ...$members);
    }

    /**
     * Create a group whose members are joined with OR.
     */
    public static function any(Condition|ConditionGroup ...$members): self
    {
        return new self(ConditionXor::Or, ...$members);
    }

    public function add(Condition|ConditionGroup ...$members): self
    {
        foreach ($members as $member) {
            $this->members[] = $member;
        }

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->members === [];
    }
}
