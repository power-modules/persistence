<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit;

use Modular\Persistence\Database\PostgresDatabase;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

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

        $pdo->expects(self::once())
            ->method('exec')
            ->with('SET search_path TO "my_schema";')
            ->willReturn(0);

        $database = new PostgresDatabase($pdo);
        $database->setSearchPath('my_schema');
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
}
