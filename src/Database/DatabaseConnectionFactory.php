<?php

declare(strict_types=1);

namespace Modular\Persistence\Database;

use Modular\Persistence\Config\Config;
use PDO;

final readonly class DatabaseConnectionFactory
{
    public function __construct(
        private Config $config,
    ) {
    }

    public function make(): IDatabase
    {
        $pdo = $this->makePdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            return new PostgresDatabase($pdo);
        }

        return new Database($pdo);
    }

    public function makePdo(): \PDO
    {
        return new PDO(
            $this->config->getDsn(),
            $this->config->getUsername(),
            $this->config->getPassword(),
            $this->config->getOptions(),
        );
    }
}
