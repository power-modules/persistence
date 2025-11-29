<?php

declare(strict_types=1);

namespace Modular\Persistence;

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
        return new Database(
            $this->makePdo(),
        );
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
