<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement\Factory;

use Modular\Persistence\Repository\Statement\Contract\IDeleteStatement;
use Modular\Persistence\Repository\Statement\Contract\IInsertStatement;
use Modular\Persistence\Repository\Statement\Contract\INamespaceProvider;
use Modular\Persistence\Repository\Statement\Contract\ISelectStatement;
use Modular\Persistence\Repository\Statement\Contract\ISqlDialect;
use Modular\Persistence\Repository\Statement\Contract\IStatementFactory;
use Modular\Persistence\Repository\Statement\Contract\IUpdateStatement;
use Modular\Persistence\Repository\Statement\Dialect\PostgresDialect;
use Modular\Persistence\Repository\Statement\DeleteStatement;
use Modular\Persistence\Repository\Statement\InsertStatement;
use Modular\Persistence\Repository\Statement\SelectStatement;
use Modular\Persistence\Repository\Statement\UpdateStatement;

class GenericStatementFactory implements IStatementFactory
{
    public function __construct(
        private string|INamespaceProvider $namespace = '',
        private readonly ISqlDialect $sqlDialect = new PostgresDialect(),
    ) {
    }

    public function createSelectStatement(string $tableName): ISelectStatement
    {
        return new SelectStatement($tableName, ['*'], $this->getNamespace(), $this->sqlDialect);
    }

    public function createUpdateStatement(string $tableName): IUpdateStatement
    {
        return new UpdateStatement($tableName, $this->getNamespace(), $this->sqlDialect);
    }

    /**
     * @param array<string> $columns
     */
    public function createInsertStatement(string $tableName, array $columns): IInsertStatement
    {
        return new InsertStatement($tableName, $columns, $this->getNamespace(), $this->sqlDialect);
    }

    public function createDeleteStatement(string $tableName): IDeleteStatement
    {
        return new DeleteStatement($tableName, $this->getNamespace(), $this->sqlDialect);
    }

    private function getNamespace(): string
    {
        if ($this->namespace instanceof INamespaceProvider) {
            return $this->namespace->getNamespace();
        }

        return $this->namespace;
    }
}
