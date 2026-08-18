<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement\Contract;

use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\ConditionGroup;

interface IConditionRenderer
{
    /**
     * Render a Condition or ConditionGroup to a SQL string.
     *
     * @param (callable(string): string)|null $columnResolver Optional callback to qualify/format column names
     * @param IBindCollector|null $bindCollector Optional bind collector for parameterized queries
     */
    public function render(
        Condition|ConditionGroup $condition,
        ?callable $columnResolver = null,
        ?IBindCollector $bindCollector = null,
    ): string;
}
