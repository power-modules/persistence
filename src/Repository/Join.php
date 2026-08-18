<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository;

use Modular\Persistence\Repository\Statement\ConditionRenderer;
use Modular\Persistence\Repository\Statement\Contract\IConditionRenderer;
use Modular\Persistence\Repository\Statement\Contract\ISqlDialect;
use Modular\Persistence\Repository\Statement\Dialect\PostgresDialect;

class Join
{
    public private(set) string $localKey {
        set(\BackedEnum|string $value) => $value instanceof \BackedEnum ? (string) $value->value : $value;
    }

    public private(set) string $foreignKey {
        set(\BackedEnum|string $value) => $value instanceof \BackedEnum ? (string) $value->value : $value;
    }

    /**
     * @var array<Condition|ConditionGroup|string>
     */
    public readonly array $conditions;

    /**
     * @param Condition|ConditionGroup|array<Condition|ConditionGroup|string>|string|null $conditions
     */
    public function __construct(
        public readonly JoinType $joinType,
        public readonly string $table,
        \BackedEnum|string $localKey,
        \BackedEnum|string $foreignKey,
        public readonly ?string $localTable = null,
        public readonly ?string $alias = null,
        public readonly ?string $localKeyType = null,
        Condition|ConditionGroup|array|string|null $conditions = null,
        private readonly ?IConditionRenderer $conditionRenderer = null,
    ) {
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;

        if ($conditions instanceof Condition || $conditions instanceof ConditionGroup || is_string($conditions)) {
            $this->conditions = [$conditions];
        } elseif (is_array($conditions)) {
            $this->conditions = $conditions;
        } else {
            $this->conditions = [];
        }
    }

    /**
     * Render this join as a SQL fragment.
     *
     * @param string $defaultLocalTable Fallback table name when localTable is null
     */
    public function toSql(string $defaultLocalTable, ?ISqlDialect $sqlDialect = null): string
    {
        $sqlDialect ??= new PostgresDialect();
        $localTable = $this->localTable ?? $defaultLocalTable;

        // Build local key expression with optional safe type cast.
        // NULLIF prevents empty-string-to-type cast errors (e.g. ''::uuid).
        if ($this->localKeyType !== null) {
            $localKeyExpr = sprintf(
                'NULLIF(%s, \'\')::%s',
                $sqlDialect->qualifyIdentifier($localTable, $this->localKey),
                $this->localKeyType,
            );
        } else {
            $localKeyExpr = $sqlDialect->qualifyIdentifier($localTable, $this->localKey);
        }

        $foreignTableRef = $this->alias ?? $this->table;

        if ($this->alias === null) {
            $sql = sprintf(
                '%s JOIN %s ON %s = %s',
                $this->joinType->value,
                $sqlDialect->quoteIdentifier($this->table),
                $sqlDialect->qualifyIdentifier($foreignTableRef, $this->foreignKey),
                $localKeyExpr,
            );
        } else {
            $sql = sprintf(
                '%s JOIN %s %s ON %s = %s',
                $this->joinType->value,
                $sqlDialect->quoteIdentifier($this->table),
                $sqlDialect->quoteIdentifier($this->alias),
                $sqlDialect->qualifyIdentifier($foreignTableRef, $this->foreignKey),
                $localKeyExpr,
            );
        }

        if ($this->conditions !== []) {
            $renderer = $this->conditionRenderer ?? new ConditionRenderer();
            $columnResolver = static function (string $column) use ($foreignTableRef, $sqlDialect): string {
                $trimmed = trim($column);
                if (str_contains($trimmed, '"') || str_contains($trimmed, '.')) {
                    return $trimmed;
                }

                return $sqlDialect->qualifyIdentifier($foreignTableRef, $trimmed);
            };

            $renderedConditions = [];
            foreach ($this->conditions as $cond) {
                if (is_string($cond)) {
                    $trimmed = trim($cond);
                    if ($trimmed !== '') {
                        $renderedConditions[] = $trimmed;
                    }
                } else {
                    $rendered = $renderer->render($cond, $columnResolver);
                    if ($rendered !== '') {
                        $renderedConditions[] = $rendered;
                    }
                }
            }

            if ($renderedConditions !== []) {
                $sql .= ' AND ' . implode(' AND ', $renderedConditions);
            }
        }

        return $sql;
    }
}
