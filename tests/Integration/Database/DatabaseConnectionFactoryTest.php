<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Integration\Database;

use Modular\Persistence\Config\Config;
use Modular\Persistence\Config\Setting;
use Modular\Persistence\Database\DatabaseConnectionFactory;
use Modular\Persistence\Database\PostgresDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Integration test verifying DatabaseConnectionFactory returns the correct
 * IDatabase implementation based on the PDO driver.
 */
#[CoversClass(DatabaseConnectionFactory::class)]
final class DatabaseConnectionFactoryTest extends TestCase
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
            ->set(Setting::Options, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

        $factory = new DatabaseConnectionFactory($config);

        $db = $factory->make();

        self::assertInstanceOf(PostgresDatabase::class, $db);
    }
}
