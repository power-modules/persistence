<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement;

use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Statement\Contract\Bind;
use Modular\Persistence\Repository\Statement\Contract\IDeleteStatement;

class DeleteStatement implements IDeleteStatement
{
    protected string $statement = '';
    protected ?WhereClause $whereClause = null;

    public function __construct(
        protected string $tableName,
        protected string $namespace = '',
    ) {
    }

    public function getQuery(): string
    {
        if ($this->namespace === '') {
            $this->statement = sprintf('DELETE FROM "%s"', $this->tableName);
        } else {
            $this->statement = sprintf('DELETE FROM "%s"."%s"', $this->namespace, $this->tableName);
        }

        $this->setupWhere();

        return $this->statement;
    }

    public function addCondition(Condition ...$conditions): static
    {
        $this->getWhereClause()->add(...$conditions);

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
}
