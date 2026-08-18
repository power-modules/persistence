<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement\Contract;

interface IBindCollector
{
    /**
     * Create and record a bind parameter for the given column and value, returning the placeholder name.
     */
    public function add(string $column, mixed $value): string;

    /**
     * Create and record a JSON/JSONB bind parameter for the given column and value, returning the placeholder name.
     *
     * @param array<mixed>|string $value
     */
    public function addJson(string $column, array|string $value): string;

    /**
     * Embed/inline subquery binds with a unique subquery index.
     *
     * @return array{sql: string, binds: array<Bind>}
     */
    public function embedSubquery(ISelectStatement $subquery): array;

    /**
     * @return array<Bind>
     */
    public function getBinds(): array;

    public function reset(): void;
}
