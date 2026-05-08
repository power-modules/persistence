<?php

declare(strict_types=1);

namespace Modular\Persistence;

use Modular\Console\Contract\ProvidesConsoleCommands;
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

class PersistenceModule implements PowerModule, HasConfig, ExportsComponents, ProvidesConsoleCommands
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
        ];
    }

    public function getConsoleCommands(): array
    {
        return [
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
            static fn (DatabaseConnectionFactory $factory): Database => $factory->makeDatabase(),
        )->addArguments([
            DatabaseConnectionFactory::class,
        ]);

        $container->set(
            PostgresDatabase::class,
            static fn (DatabaseConnectionFactory $factory): PostgresDatabase => $factory->makePostgresDatabase(),
        )->addArguments([
            DatabaseConnectionFactory::class,
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
