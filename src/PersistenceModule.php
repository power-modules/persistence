<?php

declare(strict_types=1);

namespace Modular\Persistence;

use Modular\Framework\Config\Contract\HasConfig;
use Modular\Framework\Config\Contract\HasConfigTrait;
use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\ExportsComponents;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Persistence\Config\Config;
use Modular\Persistence\Console\GenerateSchemaCommand;
use Modular\Persistence\Schema\Adapter\PostgresSchemaQueryGenerator;
use PDO;

class PersistenceModule implements PowerModule, HasConfig, ExportsComponents
{
    use HasConfigTrait;

    public function __construct()
    {
        $this->powerModuleConfig = Config::create();
    }

    public static function exports(): array
    {
        return [
            DatabaseConnectionFactory::class,
            PostgresSchemaQueryGenerator::class,
            IDatabase::class,
            IPostgresDatabase::class,
            GenerateSchemaCommand::class,
        ];
    }

    public function register(ConfigurableContainerInterface $container): void
    {
        $container->set(
            DatabaseConnectionFactory::class,
            DatabaseConnectionFactory::class,
        )->addArguments([
            $this->powerModuleConfig,
        ]);

        $container->set(
            PostgresSchemaQueryGenerator::class,
            PostgresSchemaQueryGenerator::class,
        );

        $container->set(
            IDatabase::class,
            Database::class,
        )->addArguments([
            static fn (DatabaseConnectionFactory $factory): PDO => $factory->makePdo(),
        ]);

        $container->set(
            IPostgresDatabase::class,
            Database::class,
        )->addArguments([
            static fn (DatabaseConnectionFactory $factory): PDO => $factory->makePdo(),
        ]);

        $container->set(
            GenerateSchemaCommand::class,
            GenerateSchemaCommand::class,
        )->addArguments([
            PostgresSchemaQueryGenerator::class,
        ]);
    }
}
