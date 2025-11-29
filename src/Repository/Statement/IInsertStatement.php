<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement;

interface IInsertStatement extends IStatementHasParams
{
    public function getQuery(): string;

    /**
     * Set data to insert: column => value
     * @param array<string,mixed> $data
     */
    public function prepareBinds(array $data): static;

    /**
     * @return array<Bind>
     */
    public function getInsertBinds(): array;
}
