<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement;

use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Statement\Contract\Bind;
use Modular\Persistence\Repository\Statement\Contract\IDeleteStatement;
use Modular\Persistence\Repository\Statement\Contract\ISqlDialect;
use Modular\Persistence\Repository\Statement\Dialect\PostgresDialect;

class DeleteStatement implements IDeleteStatement
{
    protected string $statement = '';
    protected ?WhereClause $whereClause = null;
    protected ISqlDialect $sqlDialect;

    public function __construct(
        protected string $tableName,
        protected string $namespace = '',
        ?ISqlDialect $sqlDialect = null,
    ) {
        $this->sqlDialect = $sqlDialect ?? new PostgresDialect();
    }

    public function getQuery(): string
    {
        $this->statement = sprintf('DELETE FROM %s', $this->getQualifiedTableName());

        $this->setupWhere();

        return $this->statement;
    }

    public function addCondition(Condition ...$conditions): static
    {
        $this->getWhereClause()->add(...$conditions);

        return $this;
    }

    /**
     * @param array<Bind> $binds
     */
    public function addRawCondition(string $sql, array $binds = []): static
    {
        $this->getWhereClause()->addRaw($sql, $binds);

        return $this;
    }

    /**
     * @return array<Bind>
     */
    public function getWhereBinds(): array
    {
        return $this->getWhereClause()->getBinds();
    }

    protected function setupWhere(): static
    {
        $this->statement .= $this->getWhereClause()->toSql();

        return $this;
    }

    protected function getWhereClause(): WhereClause
    {
        if ($this->whereClause === null) {
            $this->whereClause = new WhereClause();
        }

        return $this->whereClause;
    }

    protected function getQualifiedTableName(): string
    {
        return $this->sqlDialect->qualifyIdentifier($this->namespace, $this->tableName);
    }
}
