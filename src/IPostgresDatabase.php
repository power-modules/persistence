<?php

declare(strict_types=1);

namespace Modular\Persistence;

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
     * @return array<mixed>|false
     */
    public function pgsqlGetNotify(int $fetchMode = PDO::FETCH_DEFAULT, int $timeoutMilliseconds = 0): array|false;
}
