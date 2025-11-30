<?php

declare(strict_types=1);

namespace Modular\Persistence\Database;

use Modular\Persistence\Exception\PersistenceException;

interface ITransactionManager
{
    /**
     * Initiates a transaction
     *
     * @throws PersistenceException
     */
    public function beginTransaction(): bool;

    /**
     * Commits a transaction
     *
     * @throws PersistenceException
     */
    public function commit(): bool;

    /**
     * Checks if inside a transaction
     */
    public function inTransaction(): bool;

    /**
     * Rolls back a transaction
     *
     * @throws PersistenceException
     */
    public function rollBack(): bool;
}
