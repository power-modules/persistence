<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Unit\Schema;

use InvalidArgumentException;
use Modular\Persistence\Schema\Definition\ForeignKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ForeignKey::class)]
final class ForeignKeyTest extends TestCase
{
    public function testItShouldValidateLocalColumnName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A local column name cannot be empty.');

        new ForeignKey('', 'foreignTableName', 'foreignColumnName');
    }

    public function testItShouldValidateForeignTableName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A foreign table name cannot be empty.');

        new ForeignKey('localColumnName', '', 'foreignColumnName');
    }

    public function testItShouldValidateForeignColumnName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A foreign column name cannot be empty.');

        new ForeignKey('localColumnName', 'foreignTableName', '');
    }

    public function testItShouldValidateEmptyForeignSchemaName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A foreign schema name cannot be empty when provided.');

        new ForeignKey('localColumnName', 'foreignTableName', 'foreignColumnName', '');
    }

    public function testItShouldCreateForeignKeyWithoutSchemaName(): void
    {
        $foreignKey = new ForeignKey('user_id', 'users', 'id');

        self::assertSame('user_id', $foreignKey->localColumnName);
        self::assertSame('users', $foreignKey->foreignTableName);
        self::assertSame('id', $foreignKey->foreignColumnName);
        self::assertNull($foreignKey->foreignSchemaName);
    }

    public function testItShouldCreateForeignKeyWithSchemaName(): void
    {
        $foreignKey = new ForeignKey('user_id', 'users', 'id', 'public');

        self::assertSame('user_id', $foreignKey->localColumnName);
        self::assertSame('users', $foreignKey->foreignTableName);
        self::assertSame('id', $foreignKey->foreignColumnName);
        self::assertSame('public', $foreignKey->foreignSchemaName);
    }
}
