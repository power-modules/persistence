<?php

declare(strict_types=1);

namespace Modular\Persistence;

use PDO;
use PDOException;
use PDOStatement;

class Database implements IPostgresDatabase
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
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
        $result = $this->pdo->exec($statement);

        if ($result === false) {
            throw new PDOException('Exec failed.');
        }

        return $result;
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function prepare(string $query, array $options = []): PDOStatement
    {
        return $this->pdo->prepare($query, $options);
    }

    public function query(string $query, int $fetchMode = PDO::FETCH_ASSOC): PDOStatement
    {
        $result = $this->pdo->query($query, $fetchMode);

        if ($result === false) {
            throw new PDOException('Query failed.');
        }

        return $result;
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function getSearchPath(): string
    {
        $statement = $this->query('SELECT current_setting(\'search_path\');');
        $searchPath = $statement->fetch()['current_setting'] ?? throw new PDOException('Unable to get search_path.');

        return trim($searchPath, '"');
    }

    public function setSearchPath(string $searchPath): void
    {
        $this->exec(sprintf('SET search_path TO "%s";', $searchPath));
    }

    public function pgsqlGetNotify(int $fetchMode = PDO::FETCH_DEFAULT, int $timeoutMilliseconds = 0): array|false
    {
        return $this->pdo->pgsqlGetNotify($fetchMode, $timeoutMilliseconds);
    }
}
