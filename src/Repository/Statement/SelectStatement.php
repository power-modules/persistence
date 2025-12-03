<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement;

use Modular\Persistence\Repository\Join;
use Modular\Persistence\Repository\Statement\Contract\ISelectStatement;

class SelectStatement implements ISelectStatement
{
    use TStatementHasParams;

    /**
     * @var array<Join>
     */
    protected array $join = [];

    /**
     * @var array<string>
     */
    protected array $order = [];

    /**
     * @var array<string>
     */
    protected array $groupBy = [];

    protected int $start = 0;

    protected ?int $limit = null;

    /**
     * @param array<string> $columns
     */
    public function __construct(
        protected string $tableName,
        protected array $columns = ['*'],
        protected string $namespace = '',
    ) {
    }

    public function addColumns(string ...$columns): self
    {
        $this->columns = array_unique(array_merge($this->columns, $columns));

        return $this;
    }

    public function all(?int $start = null, ?int $limit = null): string
    {
        if ($start !== null) {
            $this->start = $start;
        }

        if ($limit !== null) {
            $this->limit = $limit;
        }

        return $this->getQuery();
    }

    public function one(): string
    {
        return $this->all(0, 1);
    }

    public function count(): string
    {
        if ($this->namespace === '') {
            $this->statement = sprintf(
                'SELECT COUNT(*) as total_rows FROM "%s"',
                $this->tableName,
            );
        } else {
            $this->statement = sprintf(
                'SELECT COUNT(*) as total_rows FROM "%s"."%s"',
                $this->namespace,
                $this->tableName,
            );
        }

        $this
            ->setupJoin()
            ->setupWhere()
        ;

        return $this->statement;
    }

    public function addJoin(Join ...$joins): static
    {
        $this->join = array_merge($this->join, $joins);

        return $this;
    }

    public function unshiftJoin(Join ...$joins): static
    {
        $this->join = array_merge($joins, $this->join);

        return $this;
    }

    public function addOrder(string $field, string $dir): static
    {
        $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

        $this->order[] = sprintf('%s %s', $field, $dir);

        return $this;
    }

    public function setStart(int $start): static
    {
        $this->start = $start;

        return $this;
    }

    public function setLimit(?int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function getQuery(): string
    {
        if ($this->namespace === '') {
            $this->statement = sprintf(
                'SELECT %s FROM "%s"',
                implode(', ', $this->columns),
                $this->tableName,
            );
        } else {
            $this->statement = sprintf(
                'SELECT %s FROM "%s"."%s"',
                implode(', ', $this->columns),
                $this->namespace,
                $this->tableName,
            );
        }

        $this
            ->setupJoin()
            ->setupWhere()
            ->setupGroupBy()
            ->setupOrder()
            ->setupLimit()
        ;

        return $this->statement;
    }

    public function addGroupBy(string $field): static
    {
        $this->groupBy[] = $field;

        return $this;
    }

    protected function setupJoin(): static
    {
        if (count($this->join) > 0) {
            $joins = [];

            foreach ($this->join as $join) {
                if ($join->alias === null) {
                    $joinStatement = sprintf(
                        '%s JOIN "%s" ON "%s"."%s" = "%s"."%s"',
                        $join->joinType->value,
                        $join->table,
                        $join->table,
                        $join->foreignKey,
                        $join->localTable ?? $this->tableName,
                        $join->localKey,
                    );
                } else {
                    $joinStatement = sprintf(
                        '%s JOIN "%s" "%s" ON "%s"."%s" = "%s"."%s"',
                        $join->joinType->value,
                        $join->table,
                        $join->alias,
                        $join->alias,
                        $join->foreignKey,
                        $join->localTable ?? $this->tableName,
                        $join->localKey,
                    );
                }

                if ($join->localKeyType !== null) {
                    $joinStatement = sprintf('%s::%s', $joinStatement, $join->localKeyType);
                }

                $joins[] = $joinStatement;
            }

            $this->statement = sprintf('%s %s', $this->statement, implode(' ', $joins));
        }

        return $this;
    }

    protected function setupGroupBy(): static
    {
        if (count($this->groupBy) > 0) {
            $this->statement = sprintf('%s GROUP BY %s', $this->statement, implode(', ', $this->groupBy));
        }

        return $this;
    }

    protected function setupOrder(): static
    {
        if (count($this->order) > 0) {
            $this->statement = sprintf('%s ORDER BY %s', $this->statement, implode(', ', $this->order));
        }

        return $this;
    }

    protected function setupLimit(): static
    {
        if ($this->limit === null) {
            return $this;
        }

        $this->statement = sprintf('%s LIMIT %d OFFSET %d', $this->statement, $this->limit, $this->start);

        return $this;
    }
}
