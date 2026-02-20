<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit;

use Modular\Persistence\Database\TransactionManager;
use Modular\Persistence\Exception\PersistenceException;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransactionManager::class)]
class TransactionManagerTest extends TestCase
{
    public function testBeginTransactionSuccess(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('beginTransaction')->willReturn(true);

        $manager = new TransactionManager($pdo);
        self::assertTrue($manager->beginTransaction());
    }

    public function testBeginTransactionFailure(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('beginTransaction')->willThrowException(new PDOException('Error'));

        $manager = new TransactionManager($pdo);
        $this->expectException(PersistenceException::class);
        $manager->beginTransaction();
    }

    public function testCommitSuccess(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('commit')->willReturn(true);

        $manager = new TransactionManager($pdo);
        self::assertTrue($manager->commit());
    }

    public function testCommitFailure(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('commit')->willThrowException(new PDOException('Error'));

        $manager = new TransactionManager($pdo);
        $this->expectException(PersistenceException::class);
        $manager->commit();
    }

    public function testRollBackSuccess(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('rollBack')->willReturn(true);

        $manager = new TransactionManager($pdo);
        self::assertTrue($manager->rollBack());
    }

    public function testRollBackFailure(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('rollBack')->willThrowException(new PDOException('Error'));

        $manager = new TransactionManager($pdo);
        $this->expectException(PersistenceException::class);
        $manager->rollBack();
    }

    public function testInTransaction(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('inTransaction')->willReturn(true);

        $manager = new TransactionManager($pdo);
        self::assertTrue($manager->inTransaction());
    }
}
