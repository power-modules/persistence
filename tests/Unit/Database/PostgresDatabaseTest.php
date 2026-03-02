<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Unit\Database;

use Modular\Persistence\Database\ITransactionManager;
use Modular\Persistence\Database\PostgresDatabase;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PostgresDatabase::class)]
final class PostgresDatabaseTest extends TestCase
{
    public function testGetSearchPathStripsQuotes(): void
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

        self::assertSame('public', (new PostgresDatabase($pdo))->getSearchPath());
    }

    public function testSetSearchPathQuotesSchemaNames(): void
    {
        $pdo = $this->createMock(PDO::class);

        $pdo->expects(self::exactly(2))
            ->method('exec')
            ->willReturnCallback(function (string $statement): int {
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

    public function testUseNamespaceCachesAndAvoidsDuplicateCalls(): void
    {
        $pdo = $this->createMock(PDO::class);

        // Only 2 exec calls expected: first for my_schema, second for other_schema.
        // The repeated my_schema call should be a cache hit (no exec).
        $pdo->expects(self::exactly(2))
            ->method('exec')
            ->willReturnCallback(function (string $statement): int {
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
        $database->useNamespace('my_schema');
        $database->useNamespace('my_schema'); // cached — no exec
        $database->useNamespace('other_schema');
    }

    public function testRollBackInvalidatesNamespaceCache(): void
    {
        $pdo = $this->createMock(PDO::class);
        $transactionManager = $this->createMock(ITransactionManager::class);
        $transactionManager->expects(self::once())->method('rollBack')->willReturn(true);

        // Two identical SET statements — cache was invalidated by rollBack
        $pdo->expects(self::exactly(2))
            ->method('exec')
            ->with('SET search_path TO "my_schema"')
            ->willReturn(0);

        $database = new PostgresDatabase($pdo, $transactionManager);
        $database->useNamespace('my_schema');
        $database->rollBack();
        $database->useNamespace('my_schema'); // re-sent after cache invalidation
    }

    public function testSetSearchPathUpdatesCacheForUseNamespace(): void
    {
        $pdo = $this->createMock(PDO::class);

        // Only one exec — setSearchPath sets cache, useNamespace is a cache hit
        $pdo->expects(self::once())
            ->method('exec')
            ->with('SET search_path TO "my_schema";')
            ->willReturn(0);

        $database = new PostgresDatabase($pdo);
        $database->setSearchPath('my_schema');
        $database->useNamespace('my_schema'); // no exec — cache matches
    }
}
