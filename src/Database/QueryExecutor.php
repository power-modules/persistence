<?php

declare(strict_types=1);

namespace Modular\Persistence\Database;

use Modular\Persistence\Exception\QueryException;
use PDO;
use PDOException;
use PDOStatement;

class QueryExecutor implements IQueryExecutor
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function exec(string $statement): int
    {
        try {
            $result = $this->pdo->exec($statement);

            if ($result === false) {
                throw new PDOException('Exec failed.');
            }

            return $result;
        } catch (PDOException $e) {
            throw new QueryException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function prepare(string $query, array $options = []): PDOStatement
    {
        try {
            return $this->pdo->prepare($query, $options);
        } catch (PDOException $e) {
            throw new QueryException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function query(string $query, int $fetchMode = PDO::FETCH_ASSOC): PDOStatement
    {
        try {
            $result = $this->pdo->query($query, $fetchMode);

            if ($result === false) {
                throw new PDOException('Query failed.');
            }

            return $result;
        } catch (PDOException $e) {
            throw new QueryException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
