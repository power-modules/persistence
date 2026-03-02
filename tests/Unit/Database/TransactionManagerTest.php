<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Unit\Database;

use Modular\Persistence\Database\TransactionManager;
use Modular\Persistence\Exception\PersistenceException;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransactionManager::class)]
final class TransactionManagerTest extends TestCase
{
    public function testBeginTransactionDelegatesToPdo(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('beginTransaction')->willReturn(true);

        self::assertTrue((new TransactionManager($pdo))->beginTransaction());
    }

    public function testBeginTransactionWrappsPdoException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('beginTransaction')->willThrowException(new PDOException('Already in txn'));

        $this->expectException(PersistenceException::class);
        (new TransactionManager($pdo))->beginTransaction();
    }

    public function testCommitDelegatesToPdo(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('commit')->willReturn(true);

        self::assertTrue((new TransactionManager($pdo))->commit());
    }

    public function testCommitWrappsPdoException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('commit')->willThrowException(new PDOException('No active txn'));

        $this->expectException(PersistenceException::class);
        (new TransactionManager($pdo))->commit();
    }

    public function testRollBackDelegatesToPdo(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('rollBack')->willReturn(true);

        self::assertTrue((new TransactionManager($pdo))->rollBack());
    }

    public function testRollBackWrappsPdoException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('rollBack')->willThrowException(new PDOException('No active txn'));

        $this->expectException(PersistenceException::class);
        (new TransactionManager($pdo))->rollBack();
    }

    public function testInTransactionDelegatesToPdo(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('inTransaction')->willReturn(true);

        self::assertTrue((new TransactionManager($pdo))->inTransaction());
    }
}
