<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Unit\Database;

use Modular\Persistence\Database\IQueryExecutor;
use Modular\Persistence\Database\LoggingQueryExecutor;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(LoggingQueryExecutor::class)]
final class LoggingQueryExecutorTest extends TestCase
{
    public function testExecLogsQueryWithAffectedRows(): void
    {
        $inner = $this->createMock(IQueryExecutor::class);
        $logger = $this->createMock(LoggerInterface::class);

        $inner->expects(self::once())->method('exec')->with('DELETE FROM users')->willReturn(3);

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

        self::assertSame(3, (new LoggingQueryExecutor($inner, $logger))->exec('DELETE FROM users'));
    }

    public function testPrepareLogsStatementPreparation(): void
    {
        $inner = $this->createMock(IQueryExecutor::class);
        $logger = $this->createMock(LoggerInterface::class);
        $stmt = $this->createStub(PDOStatement::class);

        $inner->expects(self::once())->method('prepare')->with('SELECT * FROM users WHERE id = :id', [])->willReturn($stmt);

        $logger->expects(self::once())
            ->method('debug')
            ->with(
                'Statement prepared',
                self::callback(fn (array $ctx): bool => $ctx['query'] === 'SELECT * FROM users WHERE id = :id' && isset($ctx['elapsed_ms'])),
            );

        self::assertSame($stmt, (new LoggingQueryExecutor($inner, $logger))->prepare('SELECT * FROM users WHERE id = :id'));
    }

    public function testQueryLogsExecution(): void
    {
        $inner = $this->createMock(IQueryExecutor::class);
        $logger = $this->createMock(LoggerInterface::class);
        $stmt = $this->createStub(PDOStatement::class);

        $inner->expects(self::once())->method('query')->with('SELECT 1', PDO::FETCH_ASSOC)->willReturn($stmt);

        $logger->expects(self::once())
            ->method('debug')
            ->with(
                'Query executed',
                self::callback(fn (array $ctx): bool => $ctx['query'] === 'SELECT 1' && isset($ctx['elapsed_ms'])),
            );

        self::assertSame($stmt, (new LoggingQueryExecutor($inner, $logger))->query('SELECT 1'));
    }
}
