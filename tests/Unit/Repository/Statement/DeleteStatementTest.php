<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Unit\Repository\Statement;

use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Statement\DeleteStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * NEW — DeleteStatement had no dedicated unit tests before.
 */
#[CoversClass(DeleteStatement::class)]
final class DeleteStatementTest extends TestCase
{
    public function testBasicDelete(): void
    {
        $stmt = new DeleteStatement('users');

        $sql = $stmt->getQuery();

        self::assertSame('DELETE FROM "users"', $sql);
    }

    public function testDeleteWithNamespace(): void
    {
        $stmt = new DeleteStatement('users', 'my_schema');

        $sql = $stmt->getQuery();

        self::assertSame('DELETE FROM "my_schema"."users"', $sql);
    }

    public function testDeleteWithWhereCondition(): void
    {
        $stmt = new DeleteStatement('users');
        $stmt->addCondition(Condition::equals('id', 1));

        $sql = $stmt->getQuery();

        self::assertStringContainsString('DELETE FROM "users"', $sql);
        self::assertStringContainsString('WHERE (id = :w_0_id)', $sql);
    }

    public function testDeleteWhereBinds(): void
    {
        $stmt = new DeleteStatement('users');
        $stmt->addCondition(Condition::equals('status', 'inactive'));

        $binds = $stmt->getWhereBinds();

        self::assertCount(1, $binds);
        self::assertSame('inactive', $binds[0]->value);
    }

    public function testDeleteWithMultipleConditions(): void
    {
        $stmt = new DeleteStatement('logs');
        $stmt->addCondition(
            Condition::equals('level', 'debug'),
            Condition::isNull('processed_at'),
        );

        $sql = $stmt->getQuery();

        self::assertStringContainsString('WHERE (level = :w_0_level AND processed_at IS NULL)', $sql);
    }

    public function testDeleteWithNamespaceAndConditions(): void
    {
        $stmt = new DeleteStatement('sessions', 'tenant_1');
        $stmt->addCondition(Condition::equals('expired', true));

        $sql = $stmt->getQuery();

        self::assertStringContainsString('DELETE FROM "tenant_1"."sessions"', $sql);
        self::assertStringContainsString('WHERE (expired = :w_0_expired)', $sql);
    }
}
