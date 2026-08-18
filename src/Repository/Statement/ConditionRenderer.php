<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement;

use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\ConditionGroup;
use Modular\Persistence\Repository\Expression;
use Modular\Persistence\Repository\Operator;
use Modular\Persistence\Repository\Statement\Contract\IBindCollector;
use Modular\Persistence\Repository\Statement\Contract\IConditionRenderer;
use Modular\Persistence\Repository\Statement\Contract\ISelectStatement;

class ConditionRenderer implements IConditionRenderer
{
    /**
     * @param (callable(string): string)|null $columnResolver
     */
    public function render(
        Condition|ConditionGroup $condition,
        ?callable $columnResolver = null,
        ?IBindCollector $bindCollector = null,
    ): string {
        if ($condition instanceof ConditionGroup) {
            return $this->renderGroup($condition, $columnResolver, $bindCollector);
        }

        return $this->renderCondition($condition, $columnResolver, $bindCollector);
    }

    /**
     * @param (callable(string): string)|null $columnResolver
     */
    public function renderGroup(
        ConditionGroup $group,
        ?callable $columnResolver = null,
        ?IBindCollector $bindCollector = null,
    ): string {
        $parts = [];

        foreach ($group->members as $member) {
            if ($member instanceof ConditionGroup) {
                $rendered = $this->renderGroup($member, $columnResolver, $bindCollector);
                if ($rendered !== '') {
                    $parts[] = sprintf('(%s)', $rendered);
                }
            } else {
                $rendered = $this->renderCondition($member, $columnResolver, $bindCollector);
                if ($rendered !== '') {
                    $parts[] = $rendered;
                }
            }
        }

        if ($parts === []) {
            return '';
        }

        return implode(sprintf(' %s ', $group->connective->value), $parts);
    }

    /**
     * @param (callable(string): string)|null $columnResolver
     */
    public function renderCondition(
        Condition $condition,
        ?callable $columnResolver = null,
        ?IBindCollector $bindCollector = null,
    ): string {
        $column = $columnResolver !== null ? $columnResolver($condition->column) : $condition->column;
        $operator = $condition->operator;
        $value = $condition->value;

        if ($operator === Operator::Exists || $operator === Operator::NotExists) {
            if ($value instanceof ISelectStatement) {
                if ($bindCollector !== null) {
                    $embedded = $bindCollector->embedSubquery($value);

                    return sprintf('%s (%s)', $operator->value, $embedded['sql']);
                }

                return sprintf('%s (%s)', $operator->value, $value->getQuery());
            }

            return sprintf('%s (%s)', $operator->value, (string) $value);
        }

        if ($operator === Operator::IsNull || $operator === Operator::NotNull) {
            return sprintf('%s %s', $column, $operator->value);
        }

        if ($value instanceof Expression) {
            return sprintf('%s %s %s', $column, $operator->value, $value->sql);
        }

        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        if ($operator === Operator::In || $operator === Operator::NotIn) {
            if (!is_array($value) || $value === []) {
                return $operator === Operator::In ? '1 = 0' : '1 = 1';
            }

            if ($bindCollector !== null) {
                $placeholders = [];
                foreach ($value as $item) {
                    if ($item instanceof \BackedEnum) {
                        $item = $item->value;
                    }
                    $placeholders[] = $bindCollector->add($column, $item);
                }

                return sprintf('%s %s (%s)', $column, $operator->value, implode(',', $placeholders));
            }

            $items = array_map(function (mixed $item): string {
                if ($item instanceof \BackedEnum) {
                    $item = $item->value;
                }
                if (is_int($item) || is_float($item)) {
                    return (string) $item;
                }

                return sprintf("'%s'", str_replace("'", "''", (string) $item));
            }, $value);

            return sprintf('%s %s (%s)', $column, $operator->value, implode(', ', $items));
        }

        if ($operator === Operator::JsonContains || $operator === Operator::JsonContainedBy) {
            if ($bindCollector !== null) {
                $placeholder = $bindCollector->add($column, $value);

                return sprintf('%s %s %s::jsonb', $column, $operator->value, $placeholder);
            }

            $jsonStr = is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value;

            return sprintf("%s %s '%s'::jsonb", $column, $operator->value, str_replace("'", "''", $jsonStr));
        }

        if ($operator === Operator::JsonHasKey) {
            if ($bindCollector !== null) {
                $placeholder = $bindCollector->add($column, $value);

                return sprintf('jsonb_exists(%s, %s)', $column, $placeholder);
            }

            return sprintf("jsonb_exists(%s, '%s')", $column, str_replace("'", "''", (string) $value));
        }

        if ($operator === Operator::JsonHasAnyKey || $operator === Operator::JsonHasAllKeys) {
            /** @var array<string> $keys */
            $keys = $value;
            $function = $operator === Operator::JsonHasAnyKey ? 'jsonb_exists_any' : 'jsonb_exists_all';

            if ($bindCollector !== null) {
                $pgArray = '{' . implode(',', $keys) . '}';
                $placeholder = $bindCollector->add($column, $pgArray);

                return sprintf('%s(%s, %s::text[])', $function, $column, $placeholder);
            }

            $pgArray = '{' . implode(',', array_map(fn (string $k) => '"' . str_replace('"', '\"', $k) . '"', $keys)) . '}';

            return sprintf("%s(%s, '%s'::text[])", $function, $column, $pgArray);
        }

        if ($value === null) {
            return $operator === Operator::Equals ? sprintf('%s IS NULL', $column) : sprintf('%s IS NOT NULL', $column);
        }

        if (
            $operator === Operator::Like
            || $operator === Operator::NotLike
            || $operator === Operator::Ilike
            || $operator === Operator::NotIlike
        ) {
            $formattedValue = '%' . $value . '%';
            if ($bindCollector !== null) {
                $placeholder = $bindCollector->add($column, $formattedValue);

                return sprintf('%s %s %s', $column, $operator->value, $placeholder);
            }

            return sprintf("%s %s '%s'", $column, $operator->value, str_replace("'", "''", (string) $formattedValue));
        }

        if ($bindCollector !== null) {
            $placeholder = $bindCollector->add($column, $value);

            return sprintf('%s %s %s', $column, $operator->value, $placeholder);
        }

        if (is_bool($value)) {
            return sprintf('%s %s %s', $column, $operator->value, $value ? 'TRUE' : 'FALSE');
        }

        if (is_int($value) || is_float($value)) {
            return sprintf('%s %s %s', $column, $operator->value, $value);
        }

        return sprintf("%s %s '%s'", $column, $operator->value, str_replace("'", "''", (string) $value));
    }
}
