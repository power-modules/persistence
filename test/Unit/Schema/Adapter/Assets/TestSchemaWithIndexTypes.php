<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Schema\Adapter\Assets;

use Modular\Persistence\Schema\Contract\IHasIndexes;
use Modular\Persistence\Schema\Contract\ISchema;
use Modular\Persistence\Schema\Definition\ColumnDefinition;
use Modular\Persistence\Schema\Definition\Index;
use Modular\Persistence\Schema\Definition\IndexType;

enum TestSchemaWithIndexTypes: string implements ISchema, IHasIndexes
{
    case Id = 'id';
    case Email = 'email';
    case Data = 'data';
    case Location = 'location';
    case Tags = 'tags';
    case CreatedAt = 'created_at';

    public static function getTableName(): string
    {
        return 'documents';
    }

    public function getColumnDefinition(): ColumnDefinition
    {
        return match ($this) {
            self::Id => ColumnDefinition::autoincrement(self::Id)->primaryKey(),
            self::Email => ColumnDefinition::varchar(self::Email, 255)->nullable(false)->default(''),
            self::Data => ColumnDefinition::jsonb(self::Data),
            self::Location => ColumnDefinition::text(self::Location),
            self::Tags => ColumnDefinition::text(self::Tags),
            self::CreatedAt => ColumnDefinition::timestamptz(self::CreatedAt)->nullable(false),
        };
    }

    public static function getIndexes(): array
    {
        return [
            Index::make([self::Email], unique: true),
            Index::make([self::Data], type: IndexType::Gin),
            Index::make([self::Location], type: IndexType::Gist),
            Index::make([self::Tags], type: IndexType::Hash),
            Index::make([self::CreatedAt], type: IndexType::Brin),
            Index::make([self::Email, self::CreatedAt], type: IndexType::Btree),
        ];
    }
}
