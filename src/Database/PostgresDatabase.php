<?php

declare(strict_types=1);

namespace Modular\Persistence\Database;

use PDO;
use PDOException;

class PostgresDatabase extends Database implements IPostgresDatabase
{
    private ?string $currentNamespace = null;

    public function getSearchPath(): string
    {
        $statement = $this->query('SELECT current_setting(\'search_path\');');
        $searchPath = $statement->fetch()['current_setting'] ?? throw new PDOException('Unable to get search_path.');

        return trim($searchPath, '"');
    }

    public function setSearchPath(string $searchPath): void
    {
        $paths = explode(',', $searchPath);
        $sanitizedPaths = array_map(function (string $path): string {
            return $this->quoteIdentifier($path);
        }, $paths);

        $finalPath = implode(', ', $sanitizedPaths);

        $this->exec(sprintf('SET search_path TO %s;', $finalPath));

        // If we set a single path, we can cache it.
        // Otherwise, we clear the cache because useNamespace only supports single schemas.
        if (count($sanitizedPaths) === 1) {
            $this->currentNamespace = $sanitizedPaths[0];
        } else {
            $this->currentNamespace = null;
        }
    }

    public function useNamespace(string $namespace): void
    {
        $sanitized = $this->quoteIdentifier($namespace);

        if ($this->currentNamespace === $sanitized) {
            return;
        }

        $this->exec(sprintf('SET search_path TO %s', $sanitized));
        $this->currentNamespace = $sanitized;
    }

    public function rollBack(): bool
    {
        $result = parent::rollBack();
        // Invalidate the cache because ROLLBACK might have reverted the search_path
        // if it was changed inside the transaction.
        $this->currentNamespace = null;

        return $result;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return sprintf('"%s"', str_replace('"', '""', trim($identifier)));
    }

    public function pgsqlGetNotify(int $fetchMode = PDO::FETCH_DEFAULT, int $timeoutMilliseconds = 0): array|false
    {
        return $this->getPdo()->pgsqlGetNotify($fetchMode, $timeoutMilliseconds);
    }
}
