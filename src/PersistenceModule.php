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
use Modular\Persistence\Console\MakeEntityCommand;
use Modular\Persistence\Console\MakeHydratorCommand;
use Modular\Persistence\Console\MakeRepositoryCommand;
use Modular\Persistence\Console\MakeSchemaCommand;
use Modular\Persistence\Database\Database;
use Modular\Persistence\Database\DatabaseConnectionFactory;
use Modular\Persistence\Database\PostgresDatabase;
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
            Database::class,
            PostgresDatabase::class,
            GenerateSchemaCommand::class,
            MakeSchemaCommand::class,
            MakeEntityCommand::class,
            MakeHydratorCommand::class,
            MakeRepositoryCommand::class,
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
            Database::class,
            Database::class,
        )->addArguments([
            static fn (DatabaseConnectionFactory $factory): PDO => $factory->makePdo(),
        ]);

        $container->set(
            PostgresDatabase::class,
            PostgresDatabase::class,
        )->addArguments([
            static fn (DatabaseConnectionFactory $factory): PDO => $factory->makePdo(),
        ]);

        $container->set(
            GenerateSchemaCommand::class,
            GenerateSchemaCommand::class,
        )->addArguments([
            PostgresSchemaQueryGenerator::class,
        ]);

        $container->set(
            MakeSchemaCommand::class,
            MakeSchemaCommand::class,
        );

        $container->set(
            MakeEntityCommand::class,
            MakeEntityCommand::class,
        );

        $container->set(
            MakeHydratorCommand::class,
            MakeHydratorCommand::class,
        );

        $container->set(
            MakeRepositoryCommand::class,
            MakeRepositoryCommand::class,
        );
    }
}
