<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit;

use Modular\Persistence\Database\PostgresDatabase;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PostgresDatabase::class)]
class PostgresDatabaseTest extends TestCase
{
    public function testGetSearchPath(): void
    {
        $pdo = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);

        $pdo->expects(self::once())
            ->method('query')
            ->with('SELECT current_setting(\'search_path\');', PDO::FETCH_ASSOC)
            ->willReturn($statement);

        $statement->expects(self::once())
            ->method('fetch')
            ->willReturn(['current_setting' => '"public"']);

        $database = new PostgresDatabase($pdo);
        self::assertSame('public', $database->getSearchPath());
    }

    public function testSetSearchPath(): void
    {
        $pdo = $this->createMock(PDO::class);

        $pdo->expects(self::exactly(2))
            ->method('exec')
            ->willReturnCallback(function (string $statement) {
                static $count = 0;
                $count++;
                if ($count === 1) {
                    self::assertSame('SET search_path TO "my_schema";', $statement);
                } elseif ($count === 2) {
                    self::assertSame('SET search_path TO "public", "extensions";', $statement);
                }

                return 0;
            });

        $database = new PostgresDatabase($pdo);
        $database->setSearchPath('my_schema');
        $database->setSearchPath('public, extensions');
    }

    public function testPgsqlGetNotify(): void
    {
        // @phpstan-ignore-next-line
        if (!method_exists(PDO::class, 'pgsqlGetNotify')) {
            self::markTestSkipped('PDO::pgsqlGetNotify not available');
        }

        $pdo = $this->createMock(PDO::class);

        $pdo->expects(self::once())
            ->method('pgsqlGetNotify')
            ->with(PDO::FETCH_ASSOC, 100)
            ->willReturn(['message' => 'test']);

        $database = new PostgresDatabase($pdo);
        self::assertSame(['message' => 'test'], $database->pgsqlGetNotify(PDO::FETCH_ASSOC, 100));
    }

    public function testUseNamespace(): void
    {
        $pdo = $this->createMock(PDO::class);

        $pdo->expects(self::exactly(2))
            ->method('exec')
            ->willReturnCallback(function (string $statement) {
                static $count = 0;
                $count++;
                if ($count === 1) {
                    self::assertSame('SET search_path TO "my_schema"', $statement);
                } elseif ($count === 2) {
                    self::assertSame('SET search_path TO "other_schema"', $statement);
                }

                return 0;
            });

        $database = new PostgresDatabase($pdo);

        // First call, executes SET
        $database->useNamespace('my_schema');

        // Second call with same namespace, should NOT execute SET
        $database->useNamespace('my_schema');

        // Third call with different namespace, executes SET
        $database->useNamespace('other_schema');
    }

    public function testRollBackInvalidatesNamespaceCache(): void
    {
        $pdo = $this->createMock(PDO::class);
        $transactionManager = $this->createMock(\Modular\Persistence\Database\ITransactionManager::class);

        $transactionManager->expects(self::once())
            ->method('rollBack')
            ->willReturn(true);

        $pdo->expects(self::exactly(2))
            ->method('exec')
            ->with('SET search_path TO "my_schema"')
            ->willReturn(0);

        $database = new PostgresDatabase($pdo, $transactionManager);

        $database->useNamespace('my_schema');
        $database->rollBack();
        $database->useNamespace('my_schema');
    }

    public function testSetSearchPathUpdatesCache(): void
    {
        $pdo = $this->createMock(PDO::class);

        $pdo->expects(self::exactly(1))
            ->method('exec')
            ->with('SET search_path TO "my_schema";')
            ->willReturn(0);

        $database = new PostgresDatabase($pdo);

        // This should set the cache to "my_schema"
        $database->setSearchPath('my_schema');

        // This should NOT trigger another exec because the cache matches
        $database->useNamespace('my_schema');
    }
}
