<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Schema\Adapter\Assets;

use Modular\Persistence\Schema\ColumnDefinition;
use Modular\Persistence\Schema\ForeignKey;
use Modular\Persistence\Schema\IHasForeignKeys;
use Modular\Persistence\Schema\ISchema;

enum TestSchemaWithForeignSchema: string implements ISchema, IHasForeignKeys
{
    case Id = 'id';
    case UserId = 'user_id';
    case ProductId = 'product_id';

    public static function getTableName(): string
    {
        return 'orders';
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
            self::UserId => ColumnDefinition::varchar(self::UserId, 255, false),
            self::ProductId => ColumnDefinition::varchar(self::ProductId, 255, false),
        };
    }

    public static function getForeignKeys(): array
    {
        return [
            new ForeignKey(self::UserId->value, 'users', 'id', 'auth'),
            new ForeignKey(self::ProductId->value, 'products', 'id'),
        ];
    }
}
