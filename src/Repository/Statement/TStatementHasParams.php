<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement;

use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Operator;
use Modular\Persistence\Repository\Statement\Contract\Bind;
use PDO;

trait TStatementHasParams
{
    protected string $statement = '';

    /**
     * @var array<int,Condition[]>
     */
    protected array $condition = [];

    /**
     * @var array<Bind>
     */
    protected array $whereBinds = [];

    private int $idx = 0;

    public function addCondition(Condition ...$condition): static
    {
        $groupedConditions = [];

        foreach ($condition as $condition) {
            $groupedConditions[] = $condition;

            if (in_array($condition->operator, [Operator::In, Operator::NotIn]) === true) {
                foreach ($condition->value as $val) {
                    $this->whereBinds[] = $this->makeBind($condition->column, $val, 'w_' . $this->idx++);
                }
            } elseif (in_array($condition->operator, [Operator::Like, Operator::NotLike, Operator::Ilike, Operator::NotIlike]) === true) {
                $this->whereBinds[] = $this->makeBind($condition->column, '%' . $condition->value . '%', 'w_' . $this->idx++);
            } else {
                $this->whereBinds[] = $this->makeBind($condition->column, $condition->value, 'w_' . $this->idx++);
            }
        }

        if (count($groupedConditions) > 0) {
            $this->condition[] = $groupedConditions;
        }

        return $this;
    }

    /**
     * @return array<Bind>
     */
    public function getWhereBinds(): array
    {
        return $this->whereBinds;
    }

    protected function setupWhere(): static
    {
        if (count($this->condition) === 0) {
            return $this;
        }

        $whereGroups = [];
        $idx = 0;

        foreach ($this->condition as $conditionGroup) {
            $whereGroup = [];

            foreach ($conditionGroup as $condition) {
                if (in_array($condition->operator, [Operator::IsNull, Operator::NotNull]) === true) {
                    $cond = sprintf('%s %s', $condition->column, $condition->operator->value);
                    $idx++;
                } elseif (in_array($condition->operator, [Operator::In, Operator::NotIn]) === true) {
                    $placeholders = [];

                    foreach ($condition->value as $val) {
                        $placeholders[] = sprintf('%s', $this->whereBinds[$idx]->name);
                        $idx++;
                    }
                    $cond = sprintf('%s %s (%s)', $condition->column, $condition->operator->value, implode(',', $placeholders));
                } else {
                    $cond = sprintf('%s %s %s', $condition->column, $condition->operator->value, $this->whereBinds[$idx]->name);
                    $idx++;
                }

                if (count($whereGroup) > 0) {
                    $cond = sprintf('%s %s', $condition->xor->value, $cond);
                }

                $whereGroup[] = $cond;
            }

            $whereGroups[] = implode(' ', $whereGroup);
        }

        $this->statement = sprintf('%s WHERE (%s)', $this->statement, implode(') AND (', $whereGroups));

        return $this;
    }

    protected function makeBind(string $column, mixed $value, string $bindPrefix = ''): Bind
    {
        $bindName = preg_replace('/[^a-zA-Z0-9_]/', '_', $column) ?? 'param';

        $bindType = match (gettype($value)) {
            'boolean' => PDO::PARAM_BOOL,
            'integer' => PDO::PARAM_INT,
            'double' => PDO::PARAM_STR,
            'string' => PDO::PARAM_STR,
            'array' => PDO::PARAM_STR,
            'NULL' => PDO::PARAM_NULL,
            default => throw new \RuntimeException(
                sprintf('Unknown data type in condition: %s', gettype($value)),
            ),
        };

        $bindName = sprintf(':%s_%s', $bindPrefix, $bindName);

        return new Bind($column, $bindName, $value, $bindType);
    }
}
