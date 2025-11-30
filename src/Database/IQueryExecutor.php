<?php

declare(strict_types=1);

namespace Modular\Persistence\Database;

use Modular\Persistence\Exception\PersistenceException;
use PDO;
use PDOStatement;

interface IQueryExecutor
{
    /**
     * Execute an SQL statement and return the number of affected rows
     *
     * @throws PersistenceException
     */
    public function exec(string $statement): int;

    /**
     * Prepares a statement for execution and returns a statement object
     *
     * @param array<int,int> $options @see PDO::ATTR_CURSOR
     *
     * @throws PersistenceException
     */
    public function prepare(string $query, array $options = []): PDOStatement;

    /**
     * Prepares and executes an SQL statement without placeholders
     *
     * @throws PersistenceException
     */
    public function query(
        string $query,
        int $fetchMode = PDO::FETCH_ASSOC,
    ): PDOStatement;
}
