<?php

declare(strict_types=1);

namespace Modular\Persistence\Schema\Definition;

enum ColumnType
{
    case Bigint;
    case Date;
    case Decimal;
    case Int;
    case Mediumblob;
    case SmallInt;
    case Text;
    case Jsonb;
    case Timestamp;
    case TimestampTz;
    case Tinyint;
    case Uuid;
    case Varchar;

    public function getDbType(): string
    {
        return match ($this) {
            self::Bigint => 'BIGINT',
            self::Date => 'DATE',
            self::Decimal => 'DECIMAL',
            self::Int => 'INTEGER',
            self::Mediumblob => 'MEDIUMBLOB',
            self::SmallInt => 'SMALLINT',
            self::Text => 'TEXT',
            self::Jsonb => 'JSONB',
            self::Timestamp => 'TIMESTAMP',
            self::TimestampTz => 'TIMESTAMPTZ',
            self::Tinyint => 'TINYINT',
            self::Uuid => 'UUID',
            self::Varchar => 'VARCHAR',
        };
    }
}
