<?php

declare(strict_types=1);

namespace Modular\Persistence\Database;

use PDO;
use PDOException;

interface IPostgresDatabase extends IDatabase
{
    /**
     * Returns current search path. PostgreSQL only to support schemas different from "public".
     */
    public function getSearchPath(): string;

    /**
     * Sets default search path. PostgreSQL only to support schemas different from "public".
     *
     * @throws PDOException
     */
    public function setSearchPath(string $searchPath): void;

    /**
     * Switch to a specific namespace (schema).
     * This should handle sanitization and execution of "SET search_path".
     */
    public function useNamespace(string $namespace): void;

    /**
     * @return array<mixed>|false
     */
    public function pgsqlGetNotify(int $fetchMode = PDO::FETCH_DEFAULT, int $timeoutMilliseconds = 0): array|false;
}
