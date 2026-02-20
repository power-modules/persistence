<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Database;

use Modular\Persistence\Database\IQueryExecutor;
use Modular\Persistence\Database\LoggingQueryExecutor;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(LoggingQueryExecutor::class)]
class LoggingQueryExecutorTest extends TestCase
{
    public function testExecLogsQuery(): void
    {
        $inner = $this->createMock(IQueryExecutor::class);
        $logger = $this->createMock(LoggerInterface::class);

        $inner->expects(self::once())
            ->method('exec')
            ->with('DELETE FROM users')
            ->willReturn(3);

        $logger->expects(self::once())
            ->method('debug')
            ->with(
                'Query executed',
                self::callback(function (array $context): bool {
                    self::assertSame('DELETE FROM users', $context['query']);
                    self::assertArrayHasKey('elapsed_ms', $context);
                    self::assertSame(3, $context['affected_rows']);

                    return true;
                }),
            );

        $executor = new LoggingQueryExecutor($inner, $logger);
        $result = $executor->exec('DELETE FROM users');

        self::assertSame(3, $result);
    }

    public function testPrepareLogsQuery(): void
    {
        $inner = $this->createMock(IQueryExecutor::class);
        $logger = $this->createMock(LoggerInterface::class);
        $pdoStatement = $this->createStub(PDOStatement::class);

        $inner->expects(self::once())
            ->method('prepare')
            ->with('SELECT * FROM users WHERE id = :id', [])
            ->willReturn($pdoStatement);

        $logger->expects(self::once())
            ->method('debug')
            ->with(
                'Statement prepared',
                self::callback(function (array $context): bool {
                    self::assertSame('SELECT * FROM users WHERE id = :id', $context['query']);
                    self::assertArrayHasKey('elapsed_ms', $context);

                    return true;
                }),
            );

        $executor = new LoggingQueryExecutor($inner, $logger);
        $result = $executor->prepare('SELECT * FROM users WHERE id = :id');

        self::assertSame($pdoStatement, $result);
    }

    public function testQueryLogsQuery(): void
    {
        $inner = $this->createMock(IQueryExecutor::class);
        $logger = $this->createMock(LoggerInterface::class);
        $pdoStatement = $this->createStub(PDOStatement::class);

        $inner->expects(self::once())
            ->method('query')
            ->with('SELECT 1', PDO::FETCH_ASSOC)
            ->willReturn($pdoStatement);

        $logger->expects(self::once())
            ->method('debug')
            ->with(
                'Query executed',
                self::callback(function (array $context): bool {
                    self::assertSame('SELECT 1', $context['query']);
                    self::assertArrayHasKey('elapsed_ms', $context);

                    return true;
                }),
            );

        $executor = new LoggingQueryExecutor($inner, $logger);
        $result = $executor->query('SELECT 1');

        self::assertSame($pdoStatement, $result);
    }
}
