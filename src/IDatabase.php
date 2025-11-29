<?php

declare(strict_types=1);

namespace Modular\Persistence;

use PDO;
use PDOException;
use PDOStatement;

interface IDatabase
{
    /**
     * Initiates a transaction
     *
     * @throws PDOException
     */
    public function beginTransaction(): bool;

    /**
     * Commits a transaction
     *
     * @throws PDOException
     */
    public function commit(): bool;

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

    /**
     * Execute an SQL statement and return the number of affected rows
     *
     * @throws PDOException
     */
    public function exec(string $statement): int;

    /**
     * Checks if inside a transaction
     */
    public function inTransaction(): bool;

    /**
     * Prepares a statement for execution and returns a statement object
     *
     * @param array<int,int> $options @see PDO::ATTR_CURSOR
     *
     * @throws PDOException
     */
    public function prepare(string $query, array $options = []): PDOStatement;

    /**
     * Prepares and executes an SQL statement without placeholders
     *
     * @throws PDOException
     */
    public function query(
        string $query,
        int $fetchMode = PDO::FETCH_ASSOC,
    ): PDOStatement;

    /**
     * Rolls back a transaction
     *
     * @throws PDOException
     */
    public function rollBack(): bool;
}
