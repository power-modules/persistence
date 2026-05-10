<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement\Dialect;

use Modular\Persistence\Repository\Statement\Contract\ISqlDialect;

final class MysqlDialect implements ISqlDialect
{
    public function quoteIdentifier(string $identifier): string
    {
        return sprintf('`%s`', str_replace('`', '``', $identifier));
    }

    public function qualifyIdentifier(string ...$parts): string
    {
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        return implode('.', array_map($this->quoteIdentifier(...), $parts));
    }

    public function getInsertCommand(bool $ignoreDuplicates): string
    {
        return $ignoreDuplicates ? 'INSERT IGNORE INTO' : 'INSERT INTO';
    }

    public function buildInsertConflictClause(bool $ignoreDuplicates, array $conflictColumns, array $updateColumns): string
    {
        if ($ignoreDuplicates || $updateColumns === []) {
            return '';
        }

        $setClauses = array_map(
            fn (string $column): string => sprintf(
                '%s = VALUES(%s)',
                $this->quoteIdentifier($column),
                $this->quoteIdentifier($column),
            ),
            $updateColumns,
        );

        return sprintf(' ON DUPLICATE KEY UPDATE %s', implode(', ', $setClauses));
    }
}
