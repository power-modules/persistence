<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Schema;

use InvalidArgumentException;
use Modular\Persistence\Schema\ForeignKey;
use PHPUnit\Framework\TestCase;

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

        $this->assertSame('user_id', $foreignKey->localColumnName);
        $this->assertSame('users', $foreignKey->foreignTableName);
        $this->assertSame('id', $foreignKey->foreignColumnName);
        $this->assertNull($foreignKey->foreignSchemaName);
    }

    public function testItShouldCreateForeignKeyWithSchemaName(): void
    {
        $foreignKey = new ForeignKey('user_id', 'users', 'id', 'public');

        $this->assertSame('user_id', $foreignKey->localColumnName);
        $this->assertSame('users', $foreignKey->foreignTableName);
        $this->assertSame('id', $foreignKey->foreignColumnName);
        $this->assertSame('public', $foreignKey->foreignSchemaName);
    }
}
