<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit;

use Modular\Persistence\Database\QueryExecutor;
use Modular\Persistence\Exception\QueryException;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class QueryExecutorTest extends TestCase
{
    public function testExecSuccess(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('exec')->with('DELETE FROM table')->willReturn(5);

        $executor = new QueryExecutor($pdo);
        self::assertSame(5, $executor->exec('DELETE FROM table'));
    }

    public function testExecFailureReturnsFalse(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('exec')->willReturn(false);

        $executor = new QueryExecutor($pdo);
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Exec failed.');
        $executor->exec('DELETE FROM table');
    }

    public function testExecThrowsPDOException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('exec')->willThrowException(new PDOException('Error'));

        $executor = new QueryExecutor($pdo);
        $this->expectException(QueryException::class);
        $executor->exec('DELETE FROM table');
    }

    public function testPrepareSuccess(): void
    {
        $pdo = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);
        $pdo->expects(self::once())->method('prepare')->with('SELECT * FROM table', [])->willReturn($statement);

        $executor = new QueryExecutor($pdo);
        self::assertSame($statement, $executor->prepare('SELECT * FROM table'));
    }

    public function testPrepareThrowsPDOException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('prepare')->willThrowException(new PDOException('Error'));

        $executor = new QueryExecutor($pdo);
        $this->expectException(QueryException::class);
        $executor->prepare('SELECT * FROM table');
    }

    public function testQuerySuccess(): void
    {
        $pdo = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);
        $pdo->expects(self::once())->method('query')->with('SELECT * FROM table', PDO::FETCH_ASSOC)->willReturn($statement);

        $executor = new QueryExecutor($pdo);
        self::assertSame($statement, $executor->query('SELECT * FROM table'));
    }

    public function testQueryFailureReturnsFalse(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('query')->willReturn(false);

        $executor = new QueryExecutor($pdo);
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Query failed.');
        $executor->query('SELECT * FROM table');
    }

    public function testQueryThrowsPDOException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('query')->willThrowException(new PDOException('Error'));

        $executor = new QueryExecutor($pdo);
        $this->expectException(QueryException::class);
        $executor->query('SELECT * FROM table');
    }
}
