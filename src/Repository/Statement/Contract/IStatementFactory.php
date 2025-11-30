<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement\Contract;

interface IStatementFactory
{
    public function createSelectStatement(string $tableName): ISelectStatement;

    public function createUpdateStatement(string $tableName): IUpdateStatement;

    /**
     * @param array<string> $columns
     */
    public function createInsertStatement(string $tableName, array $columns): IInsertStatement;

    public function createDeleteStatement(string $tableName): IDeleteStatement;
}
