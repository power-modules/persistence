<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Schema\Adapter\Assets;

use Modular\Persistence\Schema\Contract\ISchema;
use Modular\Persistence\Schema\Definition\ColumnDefinition;

enum TestSchemaNoPrimaryKey: string implements ISchema
{
    case Name = 'name';
    case Value = 'value';

    public static function getTableName(): string
    {
        return 'no_pk_table';
    }

    public function getColumnDefinition(): ColumnDefinition
    {
        return match ($this) {
            self::Name => ColumnDefinition::varchar(self::Name, 255),
            self::Value => ColumnDefinition::int(self::Value, 11),
        };
    }
}
