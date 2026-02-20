<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement\Contract;

use Modular\Persistence\Repository\Condition;

interface IDeleteStatement
{
    public function getQuery(): string;

    public function addCondition(Condition ...$condition): static;

    /**
     * Add a raw SQL condition with optional bind values.
     *
     * @param array<Bind> $binds
     */
    public function addRawCondition(string $sql, array $binds = []): static;

    /**
     * @return array<Bind>
     */
    public function getWhereBinds(): array;
}
