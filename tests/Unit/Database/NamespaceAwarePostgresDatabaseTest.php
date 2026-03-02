<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Unit\Database;

use Modular\Persistence\Database\IPostgresDatabase;
use Modular\Persistence\Database\NamespaceAwarePostgresDatabase;
use Modular\Persistence\Repository\Statement\Contract\INamespaceProvider;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NamespaceAwarePostgresDatabase::class)]
final class NamespaceAwarePostgresDatabaseTest extends TestCase
{
    public function testPrepareSetsNamespaceBeforeDelegating(): void
    {
        $inner = $this->createMock(IPostgresDatabase::class);
        $provider = $this->createStub(INamespaceProvider::class);
        $statement = $this->createStub(PDOStatement::class);

        $provider->method('getNamespace')->willReturn('tenant_1');
        $inner->expects(self::once())->method('useNamespace')->with('tenant_1');
        $inner->expects(self::once())->method('prepare')->with('SELECT 1')->willReturn($statement);

        self::assertSame($statement, (new NamespaceAwarePostgresDatabase($inner, $provider))->prepare('SELECT 1'));
    }

    public function testQuerySetsNamespaceBeforeDelegating(): void
    {
        $inner = $this->createMock(IPostgresDatabase::class);
        $provider = $this->createStub(INamespaceProvider::class);
        $statement = $this->createStub(PDOStatement::class);

        $provider->method('getNamespace')->willReturn('tenant_2');
        $inner->expects(self::once())->method('useNamespace')->with('tenant_2');
        $inner->expects(self::once())->method('query')->with('SELECT 1', PDO::FETCH_ASSOC)->willReturn($statement);

        (new NamespaceAwarePostgresDatabase($inner, $provider))->query('SELECT 1');
    }

    public function testExecSetsNamespaceBeforeDelegating(): void
    {
        $inner = $this->createMock(IPostgresDatabase::class);
        $provider = $this->createStub(INamespaceProvider::class);

        $provider->method('getNamespace')->willReturn('tenant_3');
        $inner->expects(self::once())->method('useNamespace')->with('tenant_3');
        $inner->expects(self::once())->method('exec')->with('DELETE FROM users')->willReturn(1);

        self::assertSame(1, (new NamespaceAwarePostgresDatabase($inner, $provider))->exec('DELETE FROM users'));
    }

    public function testEmptyNamespaceSkipsUseNamespace(): void
    {
        $inner = $this->createMock(IPostgresDatabase::class);
        $provider = $this->createStub(INamespaceProvider::class);

        $provider->method('getNamespace')->willReturn('');
        $inner->expects(self::never())->method('useNamespace');
        $inner->expects(self::once())->method('exec')->with('SELECT 1')->willReturn(0);

        (new NamespaceAwarePostgresDatabase($inner, $provider))->exec('SELECT 1');
    }

    public function testBeginTransactionDelegatesToInner(): void
    {
        $inner = $this->createMock(IPostgresDatabase::class);
        $provider = $this->createStub(INamespaceProvider::class);

        $inner->expects(self::once())->method('beginTransaction')->willReturn(true);

        self::assertTrue((new NamespaceAwarePostgresDatabase($inner, $provider))->beginTransaction());
    }

    public function testCommitDelegatesToInner(): void
    {
        $inner = $this->createMock(IPostgresDatabase::class);
        $provider = $this->createStub(INamespaceProvider::class);

        $inner->expects(self::once())->method('commit')->willReturn(true);

        self::assertTrue((new NamespaceAwarePostgresDatabase($inner, $provider))->commit());
    }

    public function testRollBackDelegatesToInner(): void
    {
        $inner = $this->createMock(IPostgresDatabase::class);
        $provider = $this->createStub(INamespaceProvider::class);

        $inner->expects(self::once())->method('rollBack')->willReturn(true);

        self::assertTrue((new NamespaceAwarePostgresDatabase($inner, $provider))->rollBack());
    }

    public function testInTransactionDelegatesToInner(): void
    {
        $inner = $this->createMock(IPostgresDatabase::class);
        $provider = $this->createStub(INamespaceProvider::class);

        $inner->expects(self::once())->method('inTransaction')->willReturn(false);

        self::assertFalse((new NamespaceAwarePostgresDatabase($inner, $provider))->inTransaction());
    }
}
