<?php

declare(strict_types=1);

namespace Modular\Persistence\Database;

use Modular\Persistence\Repository\Statement\Contract\INamespaceProvider;
use PDO;
use PDOStatement;

/**
 * Decorator that ensures the correct PostgreSQL schema (namespace) is set before executing queries.
 * This enables transparent multi-tenancy support without modifying Repositories.
 */
class NamespaceAwarePostgresDatabase implements IPostgresDatabase
{
    public function __construct(
        private readonly IPostgresDatabase $database,
        private readonly INamespaceProvider $namespaceProvider,
    ) {
    }

    private function ensureNamespace(): void
    {
        $namespace = $this->namespaceProvider->getNamespace();
        // We only switch if a namespace is actually provided.
        // If the provider returns empty/null, we might want to stick to default or do nothing.
        // Assuming getNamespace() returns string.
        if ($namespace !== '') {
            $this->database->useNamespace($namespace);
        }
    }

    public function prepare(string $query, array $options = []): PDOStatement
    {
        $this->ensureNamespace();

        return $this->database->prepare($query, $options);
    }

    public function query(string $query, int $fetchMode = PDO::FETCH_ASSOC): PDOStatement
    {
        $this->ensureNamespace();

        return $this->database->query($query, $fetchMode);
    }

    public function exec(string $statement): int
    {
        $this->ensureNamespace();

        return $this->database->exec($statement);
    }

    // Delegate everything else

    public function beginTransaction(): bool
    {
        return $this->database->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->database->commit();
    }

    public function rollBack(): bool
    {
        return $this->database->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->database->inTransaction();
    }

    public function errorCode(): ?string
    {
        return $this->database->errorCode();
    }

    public function errorInfo(): array
    {
        return $this->database->errorInfo();
    }

    public function getSearchPath(): string
    {
        return $this->database->getSearchPath();
    }

    public function setSearchPath(string $searchPath): void
    {
        $this->database->setSearchPath($searchPath);
    }

    public function useNamespace(string $namespace): void
    {
        $this->database->useNamespace($namespace);
    }

    public function pgsqlGetNotify(int $fetchMode = PDO::FETCH_DEFAULT, int $timeoutMilliseconds = 0): array|false
    {
        return $this->database->pgsqlGetNotify($fetchMode, $timeoutMilliseconds);
    }
}
