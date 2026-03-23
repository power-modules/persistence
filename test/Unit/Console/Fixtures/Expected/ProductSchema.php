<?php

declare(strict_types=1);

namespace App\Schema;

use Modular\Persistence\Schema\Contract\ISchema;
use Modular\Persistence\Schema\Definition\ColumnDefinition;

enum ProductSchema: string implements ISchema
{
    case Id = 'id';
    // case CreatedAt = 'created_at';

    public static function getTableName(): string
    {
        return 'products';
    }

    public function getColumnDefinition(): ColumnDefinition
    {
        return match ($this) {
            self::Id => ColumnDefinition::uuid($this)->primaryKey(),
            // self::CreatedAt => ColumnDefinition::timestamptz($this),
        };
    }
}
