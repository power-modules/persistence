<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement\Contract;

use Modular\Persistence\Repository\Condition;

interface IDeleteStatement extends IStatementHasParams
{
    public function getQuery(): string;

    public function addCondition(Condition ...$condition): static;
}
