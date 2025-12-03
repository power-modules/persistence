<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement;

use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Statement\Contract\Bind;
use Modular\Persistence\Repository\Statement\Contract\IUpdateStatement;

class UpdateStatement implements IUpdateStatement
{
    protected string $statement = '';
    protected ?WhereClause $whereClause = null;

    /**
     * @var array<Bind>
     */
    protected array $updateBinds = [];

    private int $idx = 0;

    public function __construct(
        protected string $tableName,
        protected string $namespace = '',
    ) {
    }

    public function getQuery(): string
    {
        $update = [];

        foreach ($this->updateBinds as $bind) {
            $update[] = sprintf('%s = %s', $bind->column, $bind->name);
        }

        if ($this->namespace === '') {
            $this->statement = sprintf('UPDATE "%s" SET %s', $this->tableName, implode(', ', $update));
        } else {
            $this->statement = sprintf('UPDATE "%s"."%s" SET %s', $this->namespace, $this->tableName, implode(', ', $update));
        }

        $this->setupWhere();

        return $this->statement;
    }

    public function prepareBinds(array $data): static
    {
        foreach ($data as $column => $value) {
            $this->updateBinds[] = Bind::create($column, ':u_'. $this->idx++, $value);
        }

        return $this;
    }

    public function getUpdateBinds(): array
    {
        return $this->updateBinds;
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
