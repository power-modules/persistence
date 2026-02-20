<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Schema\Adapter\Assets;

use Modular\Persistence\Schema\Contract\IHasIndexes;
use Modular\Persistence\Schema\Contract\ISchema;
use Modular\Persistence\Schema\Definition\ColumnDefinition;
use Modular\Persistence\Schema\Definition\Index;
use Modular\Persistence\Schema\Definition\IndexType;

enum TestSchemaWithExpressionIndex: string implements ISchema, IHasIndexes
{
    case Id = 'id';
    case Metadata = 'metadata';

    public static function getTableName(): string
    {
        return 'articles';
    }

    public function getColumnDefinition(): ColumnDefinition
    {
        return match ($this) {
            self::Id => ColumnDefinition::autoincrement(self::Id)->primaryKey(),
            self::Metadata => ColumnDefinition::jsonb(self::Metadata),
        };
    }

    public static function getIndexes(): array
    {
        return [
            Index::make([self::Metadata], type: IndexType::Gin),
            Index::expression("(\"metadata\"->'keywords')", IndexType::Gin, name: 'idx_metadata_keywords'),
        ];
    }
}
