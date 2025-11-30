<?php

declare(strict_types=1);

namespace Modular\Persistence\Database;

interface IDatabase extends ITransactionManager, IQueryExecutor
{
    /**
     * Fetch the SQLSTATE associated with the last operation on the database handle
     */
    public function errorCode(): ?string;

    /**
     * Fetch extended error information associated with the last operation on the database handle
     *
     * 0: SQLSTATE error code (a five characters alphanumeric identifier defined in the ANSI SQL standard).
     *
     * 1: Driver-specific error code.
     *
     * 2: Driver-specific error message.
     *
     * @return array<mixed>
     */
    public function errorInfo(): array;
}
