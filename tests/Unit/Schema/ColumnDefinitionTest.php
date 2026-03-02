<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Unit\Schema;

use Modular\Persistence\Schema\Definition\ColumnDefinition;
use Modular\Persistence\Tests\Unit\Fixture\EmployeeSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Expanded from original: covers withName() plus all builder factories.
 */
#[CoversClass(ColumnDefinition::class)]
final class ColumnDefinitionTest extends TestCase
{
    public function testWithNameReturnsNewInstance(): void
    {
        $original = ColumnDefinition::varchar(EmployeeSchema::Id, 255);
        $renamed = $original->withName(EmployeeSchema::Name);

        self::assertNotSame($original, $renamed);
        self::assertSame('id', $original->name);
        self::assertSame('name', $renamed->name);
    }

    public function testVarcharFactory(): void
    {
        $col = ColumnDefinition::varchar(EmployeeSchema::Name, 100);

        self::assertSame('name', $col->name);
        self::assertStringContainsString('VARCHAR', $col->columnType->getDbType());
    }

    public function testTextFactory(): void
    {
        $col = ColumnDefinition::text(EmployeeSchema::Name);
        self::assertSame('TEXT', $col->columnType->getDbType());
    }

    public function testIntFactory(): void
    {
        $col = ColumnDefinition::int(EmployeeSchema::Id, 11);
        self::assertSame('INTEGER', $col->columnType->getDbType());
    }

    public function testBigintFactory(): void
    {
        $col = ColumnDefinition::bigint(EmployeeSchema::Id, 20);
        self::assertSame('BIGINT', $col->columnType->getDbType());
    }

    public function testSmallintFactory(): void
    {
        $col = ColumnDefinition::smallint(EmployeeSchema::Id);
        self::assertSame('SMALLINT', $col->columnType->getDbType());
    }

    public function testDecimalFactory(): void
    {
        $col = ColumnDefinition::decimal(EmployeeSchema::Id, 10, 2);
        self::assertSame('DECIMAL', $col->columnType->getDbType());
    }

    public function testDateFactory(): void
    {
        $col = ColumnDefinition::date(EmployeeSchema::CreatedAt);
        self::assertSame('DATE', $col->columnType->getDbType());
    }

    public function testTimestampFactory(): void
    {
        $col = ColumnDefinition::timestamp(EmployeeSchema::CreatedAt);
        self::assertSame('TIMESTAMP', $col->columnType->getDbType());
    }

    public function testTimestamptzFactory(): void
    {
        $col = ColumnDefinition::timestamptz(EmployeeSchema::CreatedAt);
        self::assertSame('TIMESTAMPTZ', $col->columnType->getDbType());
    }

    public function testUuidFactory(): void
    {
        $col = ColumnDefinition::uuid(EmployeeSchema::Id);
        self::assertSame('UUID', $col->columnType->getDbType());
    }

    public function testJsonbFactory(): void
    {
        $col = ColumnDefinition::jsonb(EmployeeSchema::Name);
        self::assertSame('JSONB', $col->columnType->getDbType());
    }

    public function testAutoincrementFactory(): void
    {
        $col = ColumnDefinition::autoincrement(EmployeeSchema::Id);
        self::assertTrue($col->isAutoincrement);
    }

    // ── Immutable Builder Methods ──

    public function testNullableReturnsSameType(): void
    {
        $original = ColumnDefinition::varchar(EmployeeSchema::Name, 255);
        $nullable = $original->nullable();

        self::assertNotSame($original, $nullable);
        self::assertTrue($nullable->nullable);
    }

    public function testNullableFalse(): void
    {
        $col = ColumnDefinition::varchar(EmployeeSchema::Name, 255)->nullable(false);
        self::assertFalse($col->nullable);
    }

    public function testPrimaryKey(): void
    {
        $col = ColumnDefinition::varchar(EmployeeSchema::Id, 255)->primaryKey();
        self::assertTrue($col->isPrimaryKey);
    }

    public function testDefaultValue(): void
    {
        $col = ColumnDefinition::varchar(EmployeeSchema::Name, 255)->default('unknown');
        self::assertSame('unknown', $col->default);
    }
}
