<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository;

class Join
{
    public private(set) string $localKey {
        set(\BackedEnum|string $value) => $value instanceof \BackedEnum ? (string) $value->value : $value;
    }

    public private(set) string $foreignKey {
        set(\BackedEnum|string $value) => $value instanceof \BackedEnum ? (string) $value->value : $value;
    }

    public function __construct(
        public readonly JoinType $joinType,
        public readonly string $table,
        \BackedEnum|string $localKey,
        \BackedEnum|string $foreignKey,
        public readonly ?string $localTable = null,
        public readonly ?string $alias = null,
        public readonly ?string $localKeyType = null,
    ) {
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;
    }

    /**
     * Render this join as a SQL fragment.
     *
     * @param string $defaultLocalTable Fallback table name when localTable is null
     */
    public function toSql(string $defaultLocalTable): string
    {
        $localTable = $this->localTable ?? $defaultLocalTable;

        // Build local key expression with optional safe type cast.
        // NULLIF prevents empty-string-to-type cast errors (e.g. ''::uuid).
        if ($this->localKeyType !== null) {
            $localKeyExpr = sprintf(
                'NULLIF("%s"."%s", \'\')::%s',
                $localTable,
                $this->localKey,
                $this->localKeyType,
            );
        } else {
            $localKeyExpr = sprintf('"%s"."%s"', $localTable, $this->localKey);
        }

        $foreignTableRef = $this->alias ?? $this->table;

        if ($this->alias === null) {
            return sprintf(
                '%s JOIN "%s" ON "%s"."%s" = %s',
                $this->joinType->value,
                $this->table,
                $foreignTableRef,
                $this->foreignKey,
                $localKeyExpr,
            );
        }

        return sprintf(
            '%s JOIN "%s" "%s" ON "%s"."%s" = %s',
            $this->joinType->value,
            $this->table,
            $this->alias,
            $foreignTableRef,
            $this->foreignKey,
            $localKeyExpr,
        );
    }
}
