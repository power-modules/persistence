<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Integration;

use Modular\Framework\App\Config\Config;
use Modular\Framework\App\Config\Setting;
use Modular\Framework\App\ModularAppBuilder;
use Modular\Persistence\Database\Database;
use Modular\Persistence\Database\DatabaseConnectionFactory;
use Modular\Persistence\PersistenceModule;
use PDO;
use PHPUnit\Framework\TestCase;

class DatabaseConnectionFactoryTest extends TestCase
{
    public function testItShouldRespectConfigFile(): void
    {
        $app = new ModularAppBuilder(__DIR__)
            ->withConfig(Config::forAppRoot(__DIR__)->set(Setting::CachePath, sys_get_temp_dir()))
            ->withModules(
                PersistenceModule::class,
            )
            ->build()
        ;

        $databaseConnectionFactory = $app->get(DatabaseConnectionFactory::class);
        $database = $databaseConnectionFactory->make();
        $database->exec('CREATE TABLE test (name varchar(255))');
        $database->exec('INSERT INTO test VALUES ("John Doe")');
        $statement = $database->prepare('SELECT * FROM test');
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        self::assertCount(1, $rows);
        self::assertSame('John Doe', $rows[0]['name']);
    }

    public function testItShouldReuseContainerResolvedInstance(): void
    {
        $app = new ModularAppBuilder(__DIR__)
            ->withConfig(Config::forAppRoot(__DIR__)->set(Setting::CachePath, sys_get_temp_dir()))
            ->withModules(
                PersistenceModule::class,
            )
            ->build()
        ;

        $database = $app->get(Database::class);
        $database->beginTransaction();
        self::assertTrue($database->inTransaction());

        $database2 = $app->get(Database::class);
        self::assertSame($database, $database2);
        self::assertTrue($database2->inTransaction());
    }
}
