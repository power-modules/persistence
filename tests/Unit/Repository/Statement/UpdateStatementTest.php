<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Unit\Repository\Statement;

use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Statement\UpdateStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * NEW — UpdateStatement had no dedicated unit tests before.
 */
#[CoversClass(UpdateStatement::class)]
final class UpdateStatementTest extends TestCase
{
    public function testBasicUpdate(): void
    {
        $stmt = new UpdateStatement('users');
        $stmt->prepareBinds(['name' => 'John', 'email' => 'john@example.com']);

        $sql = $stmt->getQuery();

        self::assertStringStartsWith('UPDATE "users" SET', $sql);
        self::assertStringContainsString('name = :u_0', $sql);
        self::assertStringContainsString('email = :u_1', $sql);
    }

    public function testUpdateWithNamespace(): void
    {
        $stmt = new UpdateStatement('users', 'my_schema');
        $stmt->prepareBinds(['name' => 'John']);

        $sql = $stmt->getQuery();

        self::assertStringContainsString('UPDATE "my_schema"."users" SET', $sql);
    }

    public function testUpdateWithWhereCondition(): void
    {
        $stmt = new UpdateStatement('users');
        $stmt->prepareBinds(['name' => 'John']);
        $stmt->addCondition(Condition::equals('id', 1));

        $sql = $stmt->getQuery();

        self::assertStringContainsString('UPDATE "users" SET', $sql);
        self::assertStringContainsString('WHERE (id = :w_0_id)', $sql);
    }

    public function testUpdateBindsAreSeparateFromWhereBinds(): void
    {
        $stmt = new UpdateStatement('users');
        $stmt->prepareBinds(['name' => 'John']);
        $stmt->addCondition(Condition::equals('id', 1));

        $updateBinds = $stmt->getUpdateBinds();
        $whereBinds = $stmt->getWhereBinds();

        self::assertCount(1, $updateBinds);
        self::assertSame('John', $updateBinds[0]->value);

        self::assertCount(1, $whereBinds);
        self::assertSame(1, $whereBinds[0]->value);
    }

    public function testUpdateWithMultipleConditions(): void
    {
        $stmt = new UpdateStatement('users');
        $stmt->prepareBinds(['status' => 'banned']);
        $stmt->addCondition(
            Condition::equals('role', 'user'),
            Condition::isNull('verified_at'),
        );

        $sql = $stmt->getQuery();

        self::assertStringContainsString('WHERE (role = :w_0_role AND verified_at IS NULL)', $sql);
    }

    public function testUpdateWithNamespaceAndConditions(): void
    {
        $stmt = new UpdateStatement('users', 'tenant_1');
        $stmt->prepareBinds(['active' => false]);
        $stmt->addCondition(Condition::equals('id', 42));

        $sql = $stmt->getQuery();

        self::assertStringContainsString('UPDATE "tenant_1"."users" SET', $sql);
        self::assertStringContainsString('WHERE (id = :w_0_id)', $sql);
    }
}
