<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Unit\Database;

use Modular\Persistence\Database\QueryExecutor;
use Modular\Persistence\Exception\QueryException;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryExecutor::class)]
final class QueryExecutorTest extends TestCase
{
    public function testExecReturnsAffectedRowCount(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('exec')->with('DELETE FROM table')->willReturn(5);

        $executor = new QueryExecutor($pdo);
        self::assertSame(5, $executor->exec('DELETE FROM table'));
    }

    public function testExecThrowsOnFalseReturn(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('exec')->willReturn(false);

        $executor = new QueryExecutor($pdo);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Exec failed.');
        $executor->exec('DELETE FROM table');
    }

    public function testExecWrappsPdoException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('exec')->willThrowException(new PDOException('Syntax error'));

        $executor = new QueryExecutor($pdo);

        $this->expectException(QueryException::class);
        $executor->exec('INVALID SQL');
    }

    public function testPrepareReturnsPdoStatement(): void
    {
        $pdo = $this->createMock(PDO::class);
        $statement = $this->createStub(PDOStatement::class);
        $pdo->expects(self::once())->method('prepare')->with('SELECT * FROM users', [])->willReturn($statement);

        $executor = new QueryExecutor($pdo);
        self::assertSame($statement, $executor->prepare('SELECT * FROM users'));
    }

    public function testPrepareWrappsPdoException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('prepare')->willThrowException(new PDOException('Bad query'));

        $executor = new QueryExecutor($pdo);

        $this->expectException(QueryException::class);
        $executor->prepare('SELECT * FROM users');
    }

    public function testQueryReturnsPdoStatement(): void
    {
        $pdo = $this->createMock(PDO::class);
        $statement = $this->createStub(PDOStatement::class);
        $pdo->expects(self::once())->method('query')->with('SELECT 1', PDO::FETCH_ASSOC)->willReturn($statement);

        $executor = new QueryExecutor($pdo);
        self::assertSame($statement, $executor->query('SELECT 1'));
    }

    public function testQueryThrowsOnFalseReturn(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('query')->willReturn(false);

        $executor = new QueryExecutor($pdo);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Query failed.');
        $executor->query('SELECT 1');
    }

    public function testQueryWrappsPdoException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('query')->willThrowException(new PDOException('Connection lost'));

        $executor = new QueryExecutor($pdo);

        $this->expectException(QueryException::class);
        $executor->query('SELECT 1');
    }
}
