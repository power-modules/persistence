<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Repository\Fixture;

use Modular\Persistence\Schema\Contract\ISchema;
use Modular\Persistence\Schema\Definition\ColumnDefinition;

enum Schema: string implements ISchema
{
    case Id = 'id';
    case Name = 'name';
    case CreatedAt = 'created_at';
    case DeletedAt = 'deleted_at';

    public static function getTableName(): string
    {
        return 'employee';
    }

    public function getColumnDefinition(): ColumnDefinition
    {
        return match ($this) {
            self::Id => ColumnDefinition::autoincrement(self::Id)->primaryKey(),
            self::Name => ColumnDefinition::varchar(self::Name, 255)->nullable(false),
            self::CreatedAt => ColumnDefinition::timestamptz(self::CreatedAt)->nullable(false),
            self::DeletedAt => ColumnDefinition::timestamptz(self::DeletedAt)->nullable(true)->default(null),
        };
    }
}
