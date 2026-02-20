<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Database;

use Modular\Persistence\Database\IPostgresDatabase;
use Modular\Persistence\Database\NamespaceAwarePostgresDatabase;
use Modular\Persistence\Repository\Statement\Contract\INamespaceProvider;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NamespaceAwarePostgresDatabase::class)]
class NamespaceAwarePostgresDatabaseTest extends TestCase
{
    public function testPrepareSetsNamespace(): void
    {
        $inner = $this->createMock(IPostgresDatabase::class);
        $provider = $this->createMock(INamespaceProvider::class);
        $statement = $this->createStub(PDOStatement::class);

        $provider->expects(self::once())
            ->method('getNamespace')
            ->willReturn('tenant_1');

        $inner->expects(self::once())
            ->method('useNamespace')
            ->with('tenant_1');

        $inner->expects(self::once())
            ->method('prepare')
            ->with('SELECT 1')
            ->willReturn($statement);

        $db = new NamespaceAwarePostgresDatabase($inner, $provider);
        $db->prepare('SELECT 1');
    }

    public function testQuerySetsNamespace(): void
    {
        $inner = $this->createMock(IPostgresDatabase::class);
        $provider = $this->createMock(INamespaceProvider::class);
        $statement = $this->createStub(PDOStatement::class);

        $provider->expects(self::once())
            ->method('getNamespace')
            ->willReturn('tenant_2');

        $inner->expects(self::once())
            ->method('useNamespace')
            ->with('tenant_2');

        $inner->expects(self::once())
            ->method('query')
            ->with('SELECT 1')
            ->willReturn($statement);

        $db = new NamespaceAwarePostgresDatabase($inner, $provider);
        $db->query('SELECT 1');
    }

    public function testExecSetsNamespace(): void
    {
        $inner = $this->createMock(IPostgresDatabase::class);
        $provider = $this->createMock(INamespaceProvider::class);

        $provider->expects(self::once())
            ->method('getNamespace')
            ->willReturn('tenant_3');

        $inner->expects(self::once())
            ->method('useNamespace')
            ->with('tenant_3');

        $inner->expects(self::once())
            ->method('exec')
            ->with('DELETE FROM users')
            ->willReturn(1);

        $db = new NamespaceAwarePostgresDatabase($inner, $provider);
        $db->exec('DELETE FROM users');
    }
}
