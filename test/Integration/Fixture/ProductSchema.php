<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Integration\Fixture;

use Modular\Persistence\Schema\Contract\IHasIndexes;
use Modular\Persistence\Schema\Contract\ISchema;
use Modular\Persistence\Schema\Definition\ColumnDefinition;
use Modular\Persistence\Schema\Definition\Index;
use Modular\Persistence\Schema\Definition\IndexType;

enum ProductSchema: string implements ISchema, IHasIndexes
{
    case Id = 'id';
    case Name = 'name';
    case Metadata = 'metadata';
    case Tags = 'tags';
    case CreatedAt = 'created_at';

    public static function getTableName(): string
    {
        return 'test_product';
    }

    public function getColumnDefinition(): ColumnDefinition
    {
        return match ($this) {
            self::Id => ColumnDefinition::uuid(self::Id)->primaryKey(),
            self::Name => ColumnDefinition::varchar(self::Name, 255)->nullable(false),
            self::Metadata => ColumnDefinition::jsonb(self::Metadata)->nullable(true)->default(null),
            self::Tags => ColumnDefinition::jsonb(self::Tags)->nullable(true)->default(null),
            self::CreatedAt => ColumnDefinition::timestamptz(self::CreatedAt)->nullable(false),
        };
    }

    /**
     * @return array<Index>
     */
    public static function getIndexes(): array
    {
        return [
            Index::expression('("metadata")', IndexType::Gin, name: 'idx_test_product_metadata_gin'),
        ];
    }
}
