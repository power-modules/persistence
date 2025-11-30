<?php

declare(strict_types=1);

namespace Modular\Persistence\Database;

use PDO;
use PDOException;

class PostgresDatabase extends Database implements IPostgresDatabase
{
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
        return $this->getPdo()->pgsqlGetNotify($fetchMode, $timeoutMilliseconds);
    }
}
