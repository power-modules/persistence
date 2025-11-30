<?php

declare(strict_types=1);

namespace Modular\Persistence\Database;

use PDO;
use PDOStatement;

class Database implements IDatabase, ITransactionManager, IQueryExecutor
{
    private ITransactionManager $transactionManager;
    private IQueryExecutor $queryExecutor;

    public function __construct(
        private PDO $pdo,
        ?ITransactionManager $transactionManager = null,
        ?IQueryExecutor $queryExecutor = null,
    ) {
        $this->transactionManager = $transactionManager ?? new TransactionManager($pdo);
        $this->queryExecutor = $queryExecutor ?? new QueryExecutor($pdo);
    }

    protected function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function beginTransaction(): bool
    {
        return $this->transactionManager->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->transactionManager->commit();
    }

    public function errorCode(): ?string
    {
        return $this->pdo->errorCode();
    }

    public function errorInfo(): array
    {
        return $this->pdo->errorInfo();
    }

    public function exec(string $statement): int
    {
        return $this->queryExecutor->exec($statement);
    }

    public function inTransaction(): bool
    {
        return $this->transactionManager->inTransaction();
    }

    public function prepare(string $query, array $options = []): PDOStatement
    {
        return $this->queryExecutor->prepare($query, $options);
    }

    public function query(string $query, int $fetchMode = PDO::FETCH_ASSOC): PDOStatement
    {
        return $this->queryExecutor->query($query, $fetchMode);
    }

    public function rollBack(): bool
    {
        return $this->transactionManager->rollBack();
    }
}
