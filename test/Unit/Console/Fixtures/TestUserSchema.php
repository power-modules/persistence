<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Console\Fixtures;

use Modular\Persistence\Schema\Contract\ISchema;
use Modular\Persistence\Schema\Definition\ColumnDefinition;

enum TestUserSchema: string implements ISchema
{
    case Id = 'id';
    case Name = 'name';
    case Email = 'email';
    case Age = 'age';
    case IsActive = 'is_active';
    case CreatedAt = 'created_at';

    public static function getTableName(): string
    {
        return 'users';
    }

    public function getColumnDefinition(): ColumnDefinition
    {
        return match ($this) {
            self::Id => ColumnDefinition::uuid($this)->primaryKey(),
            self::Name => ColumnDefinition::varchar($this),
            self::Email => ColumnDefinition::varchar($this),
            self::Age => ColumnDefinition::int($this, length: 11)->nullable(),
            self::IsActive => ColumnDefinition::tinyint($this),
            self::CreatedAt => ColumnDefinition::timestamptz($this),
        };
    }
}
