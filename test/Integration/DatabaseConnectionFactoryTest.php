<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Integration;

use Modular\Persistence\Config\Config;
use Modular\Persistence\Config\Setting;
use Modular\Persistence\Database\DatabaseConnectionFactory;
use Modular\Persistence\Database\PostgresDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseConnectionFactory::class)]
class DatabaseConnectionFactoryTest extends TestCase
{
    public function testMakeReturnsPostgresDatabaseForPgsqlDsn(): void
    {
        $dsn = getenv('DB_DSN') ?: 'pgsql:host=127.0.0.1;port=15432;dbname=persistence_test';
        $user = getenv('DB_USER') ?: 'persistence_test';
        $password = getenv('DB_PASSWORD') ?: 'persistence_test';

        $config = Config::create()
            ->set(Setting::Dsn, $dsn)
            ->set(Setting::Username, $user)
            ->set(Setting::Password, $password)
        ;

        try {
            $factory = new DatabaseConnectionFactory($config);
            $database = $factory->make();
        } catch (\PDOException $e) {
            self::markTestSkipped('PostgreSQL is not available: ' . $e->getMessage());
        }

        self::assertInstanceOf(PostgresDatabase::class, $database);
    }

    public function testBasicCrudThroughFactory(): void
    {
        $dsn = getenv('DB_DSN') ?: 'pgsql:host=127.0.0.1;port=15432;dbname=persistence_test';
        $user = getenv('DB_USER') ?: 'persistence_test';
        $password = getenv('DB_PASSWORD') ?: 'persistence_test';

        $config = Config::create()
            ->set(Setting::Dsn, $dsn)
            ->set(Setting::Username, $user)
            ->set(Setting::Password, $password)
        ;

        try {
            $factory = new DatabaseConnectionFactory($config);
            $database = $factory->make();
        } catch (\PDOException $e) {
            self::markTestSkipped('PostgreSQL is not available: ' . $e->getMessage());
        }

        $database->exec('DROP TABLE IF EXISTS "test_factory_crud"');
        $database->exec('CREATE TABLE "test_factory_crud" ("name" VARCHAR(255) NOT NULL)');
        $database->exec('INSERT INTO "test_factory_crud" VALUES (\'John Doe\')');

        $statement = $database->prepare('SELECT * FROM "test_factory_crud"');
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        self::assertCount(1, $rows);
        self::assertSame('John Doe', $rows[0]['name']);

        $database->exec('DROP TABLE IF EXISTS "test_factory_crud"');
    }
}
