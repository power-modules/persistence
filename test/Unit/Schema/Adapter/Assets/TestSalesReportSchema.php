<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Schema\Adapter\Assets;

use Modular\Persistence\Schema\ColumnDefinition;
use Modular\Persistence\Schema\ForeignKey;
use Modular\Persistence\Schema\IHasForeignKeys;
use Modular\Persistence\Schema\IHasIndexes;
use Modular\Persistence\Schema\Index;
use Modular\Persistence\Schema\ISchema;

enum TestSalesReportSchema: string implements ISchema, IHasIndexes, IHasForeignKeys
{
    case Id = 'id';
    case OrderNumber = 'order_number';
    case OrderChargedDate = 'order_charged_date';
    case OrderChargedTimestamp = 'order_charged_timestamp';
    case ProductId = 'product_id';
    case ItemPrice = 'item_price';
    case CountryOfBuyer = 'country_of_buyer';

    public static function getTableName(): string
    {
        return 'sales_report';
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
            self::OrderNumber => ColumnDefinition::varchar(self::OrderNumber, 255, false, ''),
            self::OrderChargedDate => ColumnDefinition::date(self::OrderChargedDate, false),
            self::OrderChargedTimestamp => ColumnDefinition::timestamp(self::OrderChargedTimestamp, false),
            self::ProductId => ColumnDefinition::varchar(self::ProductId, 255, false, ''),
            self::ItemPrice => ColumnDefinition::decimal(self::ItemPrice, 12, 2, false, 0),
            self::CountryOfBuyer => ColumnDefinition::varchar(self::CountryOfBuyer),
        };
    }

    public static function getIndexes(): array
    {
        return [
            Index::make([self::OrderNumber]),
            Index::make([self::OrderChargedDate, self::CountryOfBuyer]),
        ];
    }

    public static function getForeignKeys(): array
    {
        return [
            new ForeignKey(self::ProductId->value, 'product', 'id'),
            new ForeignKey(self::CountryOfBuyer->value, 'country', 'code'),
        ];
    }
}
