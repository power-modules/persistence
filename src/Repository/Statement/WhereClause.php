<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement;

use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Operator;
use Modular\Persistence\Repository\Statement\Contract\Bind;

class WhereClause
{
    /**
     * @var array<int,Condition[]>
     */
    private array $conditions = [];

    /**
     * @var array<Bind>
     */
    private array $binds = [];

    /**
     * @var array<array{sql: string, binds: array<Bind>}>
     */
    private array $rawConditions = [];

    private int $paramIndex = 0;

    public function add(Condition ...$conditions): self
    {
        if (count($conditions) === 0) {
            return $this;
        }

        $this->conditions[] = array_values($conditions);

        foreach ($conditions as $condition) {
            $this->processBind($condition);
        }

        return $this;
    }

    /**
     * Add a raw SQL condition with optional bind values.
     *
     * The SQL fragment is included as an AND-joined group in the WHERE clause.
     * Use this for expressions that cannot be represented via Condition (e.g. JSONB operators with casts).
     *
     * @param array<Bind> $binds
     */
    public function addRaw(string $sql, array $binds = []): self
    {
        $this->rawConditions[] = ['sql' => $sql, 'binds' => $binds];

        return $this;
    }

    public function toSql(): string
    {
        if (count($this->conditions) === 0 && count($this->rawConditions) === 0) {
            return '';
        }

        $groups = [];
        $bindIndex = 0;

        foreach ($this->conditions as $group) {
            $groupSql = [];

            foreach ($group as $condition) {
                $sql = $this->buildConditionSql($condition, $bindIndex);

                if (count($groupSql) > 0) {
                    $sql = sprintf('%s %s', $condition->xor->value, $sql);
                }

                $groupSql[] = $sql;
            }

            $groups[] = implode(' ', $groupSql);
        }

        foreach ($this->rawConditions as $raw) {
            $groups[] = $raw['sql'];
        }

        return sprintf(' WHERE (%s)', implode(') AND (', $groups));
    }

    /**
     * @return array<Bind>
     */
    public function getBinds(): array
    {
        $rawBinds = [];
        foreach ($this->rawConditions as $raw) {
            $rawBinds = array_merge($rawBinds, $raw['binds']);
        }

        return array_merge($this->binds, $rawBinds);
    }

    private function processBind(Condition $condition): void
    {
        if (in_array($condition->operator, [Operator::IsNull, Operator::NotNull, Operator::Exists], true)) {
            return;
        }

        if (in_array($condition->operator, [Operator::In, Operator::NotIn], true)) {
            foreach ($condition->value as $val) {
                $this->binds[] = $this->createBind($condition->column, $val);
            }

            return;
        }

        if (in_array($condition->operator, [Operator::JsonHasAnyKey, Operator::JsonHasAllKeys], true)) {
            /** @var array<string> $keys */
            $keys = $condition->value;
            $pgArray = '{' . implode(',', $keys) . '}';
            $this->binds[] = $this->createBind($condition->column, $pgArray);

            return;
        }

        $value = $condition->value;
        if (in_array($condition->operator, [Operator::Like, Operator::NotLike, Operator::Ilike, Operator::NotIlike], true)) {
            $value = '%' . $value . '%';
        }

        $this->binds[] = $this->createBind($condition->column, $value);
    }

    private function buildConditionSql(Condition $condition, int &$bindIndex): string
    {
        if ($condition->operator === Operator::Exists) {
            return sprintf('EXISTS (%s)', $condition->value);
        }

        if (in_array($condition->operator, [Operator::IsNull, Operator::NotNull], true)) {
            return sprintf('%s %s', $condition->column, $condition->operator->value);
        }

        if (in_array($condition->operator, [Operator::In, Operator::NotIn], true)) {
            $placeholders = [];
            foreach ($condition->value as $ignored) {
                $placeholders[] = $this->binds[$bindIndex++]->name;
            }

            return sprintf('%s %s (%s)', $condition->column, $condition->operator->value, implode(',', $placeholders));
        }

        if (in_array($condition->operator, [Operator::JsonContains, Operator::JsonContainedBy], true)) {
            return sprintf('%s %s %s::jsonb', $condition->column, $condition->operator->value, $this->binds[$bindIndex++]->name);
        }

        if ($condition->operator === Operator::JsonHasKey) {
            return sprintf('%s %s %s', $condition->column, $condition->operator->value, $this->binds[$bindIndex++]->name);
        }

        if (in_array($condition->operator, [Operator::JsonHasAnyKey, Operator::JsonHasAllKeys], true)) {
            return sprintf('%s %s %s::text[]', $condition->column, $condition->operator->value, $this->binds[$bindIndex++]->name);
        }

        return sprintf('%s %s %s', $condition->column, $condition->operator->value, $this->binds[$bindIndex++]->name);
    }

    private function createBind(string $column, mixed $value): Bind
    {
        $cleanColumn = preg_replace('/[^a-zA-Z0-9_]/', '_', $column) ?? 'param';
        $name = sprintf(':w_%d_%s', $this->paramIndex++, $cleanColumn);

        return Bind::create($column, $name, $value);
    }
}
