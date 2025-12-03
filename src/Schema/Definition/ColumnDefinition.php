<?php

declare(strict_types=1);

namespace Modular\Persistence\Schema\Definition;

use BackedEnum;

final readonly class ColumnDefinition
{
    private function __construct(
        public string $name,
        public ColumnType $columnType,
        public mixed $default = null,
        public bool $nullable = true,
        public int $precision = 0,
        public int $scale = 0,
        public bool $isAutoincrement = false,
        public bool $isPrimaryKey = false,
    ) {
    }

    public function primaryKey(): self
    {
        return new self(
            $this->name,
            $this->columnType,
            $this->default,
            $this->nullable,
            $this->precision,
            $this->scale,
            $this->isAutoincrement,
            true,
        );
    }

    public function nullable(bool $nullable = true): self
    {
        return new self(
            $this->name,
            $this->columnType,
            $this->default,
            $nullable,
            $this->precision,
            $this->scale,
            $this->isAutoincrement,
            $this->isPrimaryKey,
        );
    }

    public function default(mixed $default): self
    {
        return new self(
            $this->name,
            $this->columnType,
            $default,
            $this->nullable,
            $this->precision,
            $this->scale,
            $this->isAutoincrement,
            $this->isPrimaryKey,
        );
    }

    public static function autoincrement(
        BackedEnum $name,
        ColumnType $columnType = ColumnType::Bigint,
        int $length = 20,
    ): self {
        return new self((string)$name->value, $columnType, 0, false, $length, 0, true);
    }

    public static function bigint(
        BackedEnum $name,
        int $length = 20,
    ): self {
        return new self((string)$name->value, ColumnType::Bigint, null, true, $length);
    }

    public static function date(
        BackedEnum $name,
    ): self {
        return new self((string)$name->value, ColumnType::Date, '1970-01-01 00:00:01', true);
    }

    public static function decimal(
        BackedEnum $name,
        int $precision,
        int $scale,
    ): self {
        return new self((string)$name->value, ColumnType::Decimal, 0, false, $precision, $scale);
    }

    public static function int(
        BackedEnum $name,
        int $length,
    ): self {
        return new self((string)$name->value, ColumnType::Int, null, true, $length);
    }

    public static function mediumblob(
        BackedEnum $name,
    ): self {
        return new self((string)$name->value, ColumnType::Mediumblob, null, true);
    }

    public static function smallint(
        BackedEnum $name,
    ): self {
        return new self((string)$name->value, ColumnType::SmallInt, null, true);
    }

    public static function text(
        BackedEnum $name,
    ): self {
        return new self((string)$name->value, ColumnType::Text, null, true);
    }

    public static function jsonb(
        BackedEnum $name,
    ): self {
        return new self((string)$name->value, ColumnType::Jsonb, null, true);
    }

    public static function timestamp(
        BackedEnum $name,
    ): self {
        return new self((string)$name->value, ColumnType::Timestamp, '1970-01-01 00:00:01', false);
    }

    /**
     * Is not supported in PostgreSQL
     */
    public static function tinyint(
        BackedEnum $name,
        int $length = 1,
    ): self {
        return new self((string)$name->value, ColumnType::Tinyint, null, true, $length);
    }

    public static function uuid(
        BackedEnum $name,
    ): self {
        return new self((string)$name->value, ColumnType::Uuid, null, false);
    }

    public static function varchar(
        BackedEnum $name,
        int $length = 255,
    ): self {
        return new self((string)$name->value, ColumnType::Varchar, null, true, $length);
    }

    public static function timestamptz(
        BackedEnum $name,
    ): self {
        return new self((string)$name->value, ColumnType::TimestampTz, '1970-01-01 00:00:01', false);
    }

    public function withName(BackedEnum $name): ColumnDefinition
    {
        return new self(
            (string)$name->value,
            $this->columnType,
            $this->default,
            $this->nullable,
            $this->precision,
            $this->scale,
            false,
            false,
        );
    }
}
