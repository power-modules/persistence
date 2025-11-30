<?php

declare(strict_types=1);

namespace Modular\Persistence\Database;

use Modular\Persistence\Exception\PersistenceException;
use PDO;
use PDOException;

class TransactionManager implements ITransactionManager
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function beginTransaction(): bool
    {
        try {
            return $this->pdo->beginTransaction();
        } catch (PDOException $e) {
            throw new PersistenceException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function commit(): bool
    {
        try {
            return $this->pdo->commit();
        } catch (PDOException $e) {
            throw new PersistenceException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function rollBack(): bool
    {
        try {
            return $this->pdo->rollBack();
        } catch (PDOException $e) {
            throw new PersistenceException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
