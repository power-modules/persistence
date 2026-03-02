<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Unit\Repository\Statement;

use Modular\Persistence\Repository\Statement\InsertStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InsertStatement::class)]
final class InsertStatementTest extends TestCase
{
    public function testBasicInsert(): void
    {
        $statement = new InsertStatement('users', ['name', 'email']);
        $statement->prepareBinds(['name' => 'John', 'email' => 'john@example.com']);

        $sql = $statement->getQuery();
        self::assertStringStartsWith('INSERT INTO "users" ("name", "email") VALUES', $sql);
        self::assertStringContainsString('(:i_0,:i_1)', $sql);
    }

    public function testInsertWithNamespace(): void
    {
        $statement = new InsertStatement('users', ['name'], 'my_schema');
        $statement->prepareBinds(['name' => 'John']);

        $sql = $statement->getQuery();
        self::assertStringStartsWith('INSERT INTO "my_schema"."users" ("name") VALUES', $sql);
    }

    public function testIgnoreDuplicates(): void
    {
        $statement = new InsertStatement('users', ['name']);
        $statement->prepareBinds(['name' => 'John']);
        $statement->ignoreDuplicates();

        $sql = $statement->getQuery();
        self::assertStringEndsWith('ON CONFLICT DO NOTHING', $sql);
    }

    public function testOnConflictUpdate(): void
    {
        $statement = new InsertStatement('users', ['id', 'name', 'email']);
        $statement->prepareBinds(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
        $statement->onConflictUpdate(['id'], ['name', 'email']);

        $sql = $statement->getQuery();

        self::assertStringContainsString(
            'ON CONFLICT ("id") DO UPDATE SET "name" = EXCLUDED."name", "email" = EXCLUDED."email"',
            $sql,
        );
    }

    public function testOnConflictUpdateWithMultipleConflictColumns(): void
    {
        $statement = new InsertStatement('user_roles', ['user_id', 'role_id', 'assigned_at']);
        $statement->prepareBinds(['user_id' => 1, 'role_id' => 2, 'assigned_at' => '2023-01-01']);
        $statement->onConflictUpdate(['user_id', 'role_id'], ['assigned_at']);

        $sql = $statement->getQuery();

        self::assertStringContainsString(
            'ON CONFLICT ("user_id", "role_id") DO UPDATE SET "assigned_at" = EXCLUDED."assigned_at"',
            $sql,
        );
    }
}
