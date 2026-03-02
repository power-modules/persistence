<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Unit\Repository\Statement;

use Modular\Persistence\Repository\Statement\DeleteStatement;
use Modular\Persistence\Repository\Statement\Factory\GenericStatementFactory;
use Modular\Persistence\Repository\Statement\InsertStatement;
use Modular\Persistence\Repository\Statement\Provider\RuntimeNamespaceProvider;
use Modular\Persistence\Repository\Statement\SelectStatement;
use Modular\Persistence\Repository\Statement\UpdateStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GenericStatementFactory::class)]
final class GenericStatementFactoryTest extends TestCase
{
    public function testCreateSelectStatement(): void
    {
        $factory = new GenericStatementFactory();
        $statement = $factory->createSelectStatement('users');
        self::assertInstanceOf(SelectStatement::class, $statement);
    }

    public function testCreateUpdateStatement(): void
    {
        $factory = new GenericStatementFactory();
        $statement = $factory->createUpdateStatement('users');
        self::assertInstanceOf(UpdateStatement::class, $statement);
    }

    public function testCreateInsertStatement(): void
    {
        $factory = new GenericStatementFactory();
        $statement = $factory->createInsertStatement('users', ['name', 'email']);
        self::assertInstanceOf(InsertStatement::class, $statement);
    }

    public function testCreateDeleteStatement(): void
    {
        $factory = new GenericStatementFactory();
        $statement = $factory->createDeleteStatement('users');
        self::assertInstanceOf(DeleteStatement::class, $statement);
    }

    public function testCreateStatementsWithNamespace(): void
    {
        $factory = new GenericStatementFactory('my_schema');

        $select = $factory->createSelectStatement('users');
        self::assertStringContainsString('"my_schema"."users"', $select->getQuery());

        $update = $factory->createUpdateStatement('users');
        $update->prepareBinds(['name' => 'John']);
        self::assertStringContainsString('"my_schema"."users"', $update->getQuery());

        $insert = $factory->createInsertStatement('users', ['name']);
        $insert->prepareBinds(['name' => 'John']);
        self::assertStringContainsString('"my_schema"."users"', $insert->getQuery());

        $delete = $factory->createDeleteStatement('users');
        self::assertStringContainsString('"my_schema"."users"', $delete->getQuery());
    }

    public function testCreateStatementsWithRuntimeNamespaceProvider(): void
    {
        $provider = new RuntimeNamespaceProvider();
        $factory = new GenericStatementFactory($provider);

        // Initially empty — no schema prefix
        $select = $factory->createSelectStatement('users');
        self::assertStringNotContainsString('"."', $select->getQuery());
        self::assertStringNotContainsString('"users"."users"', $select->getQuery());

        // Set namespace
        $provider->setNamespace('tenant_1');
        $select = $factory->createSelectStatement('users');
        self::assertStringContainsString('"tenant_1"."users"', $select->getQuery());

        // Change namespace
        $provider->setNamespace('tenant_2');
        $update = $factory->createUpdateStatement('users');
        $update->prepareBinds(['name' => 'John']);
        self::assertStringContainsString('"tenant_2"."users"', $update->getQuery());
    }
}
