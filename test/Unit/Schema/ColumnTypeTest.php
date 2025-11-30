<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Schema;

use Modular\Persistence\Schema\Definition\ColumnType;
use PHPUnit\Framework\TestCase;

final class ColumnTypeTest extends TestCase
{
    public function testGetDbType(): void
    {
        foreach (ColumnType::cases() as $columnType) {
            $expected = match ($columnType) {
                ColumnType::SmallInt => 'SMALLINT',
                ColumnType::Text => 'TEXT',
                ColumnType::Bigint => 'BIGINT',
                ColumnType::Date => 'DATE',
                ColumnType::Decimal => 'DECIMAL',
                ColumnType::Int => 'INTEGER',
                ColumnType::Mediumblob => 'MEDIUMBLOB',
                ColumnType::Timestamp => 'TIMESTAMP',
                ColumnType::TimestampTz => 'TIMESTAMPTZ',
                ColumnType::Tinyint => 'TINYINT',
                ColumnType::Uuid => 'UUID',
                ColumnType::Jsonb => 'JSONB',
                ColumnType::Varchar => 'VARCHAR',
            };

            self::assertSame($expected, $columnType->getDbType());
        }
    }
}
