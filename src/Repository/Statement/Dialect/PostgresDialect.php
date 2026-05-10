<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement\Dialect;

use Modular\Persistence\Repository\Statement\Contract\ISqlDialect;

final class PostgresDialect implements ISqlDialect
{
    public function quoteIdentifier(string $identifier): string
    {
        return sprintf('"%s"', str_replace('"', '""', $identifier));
    }

    public function qualifyIdentifier(string ...$parts): string
    {
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        return implode('.', array_map($this->quoteIdentifier(...), $parts));
    }

    public function getInsertCommand(bool $ignoreDuplicates): string
    {
        return 'INSERT INTO';
    }

    public function buildInsertConflictClause(bool $ignoreDuplicates, array $conflictColumns, array $updateColumns): string
    {
        if ($ignoreDuplicates) {
            return ' ON CONFLICT DO NOTHING';
        }

        if ($updateColumns === []) {
            return '';
        }

        $setClauses = array_map(
            fn (string $column): string => sprintf(
                '%s = EXCLUDED.%s',
                $this->quoteIdentifier($column),
                $this->quoteIdentifier($column),
            ),
            $updateColumns,
        );

        return sprintf(
            ' ON CONFLICT (%s) DO UPDATE SET %s',
            implode(', ', array_map($this->quoteIdentifier(...), $conflictColumns)),
            implode(', ', $setClauses),
        );
    }
}
