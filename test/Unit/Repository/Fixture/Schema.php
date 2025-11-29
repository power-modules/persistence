<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Repository\Fixture;

use Modular\Persistence\Schema\ColumnDefinition;
use Modular\Persistence\Schema\ISchema;

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
            self::Id => ColumnDefinition::varchar(self::Id, 36, false),
            self::Name => ColumnDefinition::varchar(self::Name, 255, false),
            self::CreatedAt => ColumnDefinition::timestamp(self::CreatedAt, false),
            self::DeletedAt => ColumnDefinition::timestamp(self::DeletedAt, true, null),
        };
    }
}
