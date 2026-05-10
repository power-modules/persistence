<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement\Contract;

interface ISqlDialect
{
    public function quoteIdentifier(string $identifier): string;

    public function qualifyIdentifier(string ...$parts): string;

    public function getInsertCommand(bool $ignoreDuplicates): string;

    /**
     * @param array<string> $conflictColumns
     * @param array<string> $updateColumns
     */
    public function buildInsertConflictClause(bool $ignoreDuplicates, array $conflictColumns, array $updateColumns): string;
}
