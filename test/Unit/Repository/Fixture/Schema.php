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

    public static function getPrimaryKey(): array
    {
        return [
            self::Id->value,
        ];
    }

    public function getColumnDefinition(): ColumnDefinition
    {
        return match ($this) {
            self::Id => ColumnDefinition::autoincrement(self::Id),
            self::Name => ColumnDefinition::varchar(self::Name, 255, false),
            self::CreatedAt => ColumnDefinition::timestamptz(self::CreatedAt, false),
            self::DeletedAt => ColumnDefinition::timestamptz(self::DeletedAt, true, null),
        };
    }
}
