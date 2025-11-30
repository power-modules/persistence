<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Schema\Adapter\Assets;

use Modular\Persistence\Schema\Contract\IHasForeignKeys;
use Modular\Persistence\Schema\Contract\ISchema;
use Modular\Persistence\Schema\Definition\ColumnDefinition;
use Modular\Persistence\Schema\Definition\ForeignKey;

enum TestSchemaWithForeignSchema: string implements ISchema, IHasForeignKeys
{
    case Id = 'id';
    case UserId = 'user_id';
    case ProductId = 'product_id';

    public static function getTableName(): string
    {
        return 'orders';
    }

    public function getColumnDefinition(): ColumnDefinition
    {
        return match ($this) {
            self::Id => ColumnDefinition::autoincrement(self::Id)->primaryKey(),
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
