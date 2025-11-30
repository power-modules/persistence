<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement\Contract;

use Modular\Persistence\Repository\Condition;

interface IUpdateStatement extends IStatementHasParams
{
    public function getQuery(): string;

    public function addCondition(Condition ...$condition): static;

    /**
     * Set data to update: column => value
     * @param array<string,mixed> $data
     */
    public function prepareBinds(array $data): static;

    /**
     * @return array<Bind>
     */
    public function getUpdateBinds(): array;
}
