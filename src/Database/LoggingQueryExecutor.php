<?php

declare(strict_types=1);

namespace Modular\Persistence\Database;

use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;

class LoggingQueryExecutor implements IQueryExecutor
{
    public function __construct(
        private readonly IQueryExecutor $inner,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function exec(string $statement): int
    {
        $start = hrtime(true);
        $result = $this->inner->exec($statement);
        $elapsed = (hrtime(true) - $start) / 1_000_000;

        $this->logger->debug('Query executed', [
            'query' => $statement,
            'elapsed_ms' => round($elapsed, 2),
            'affected_rows' => $result,
        ]);

        return $result;
    }

    public function prepare(string $query, array $options = []): PDOStatement
    {
        $start = hrtime(true);
        $result = $this->inner->prepare($query, $options);
        $elapsed = (hrtime(true) - $start) / 1_000_000;

        $this->logger->debug('Statement prepared', [
            'query' => $query,
            'elapsed_ms' => round($elapsed, 2),
        ]);

        return $result;
    }

    public function query(string $query, int $fetchMode = PDO::FETCH_ASSOC): PDOStatement
    {
        $start = hrtime(true);
        $result = $this->inner->query($query, $fetchMode);
        $elapsed = (hrtime(true) - $start) / 1_000_000;

        $this->logger->debug('Query executed', [
            'query' => $query,
            'elapsed_ms' => round($elapsed, 2),
        ]);

        return $result;
    }
}
