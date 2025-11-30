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
    ) {
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
        bool $nullable = true,
        ?int $default = null,
    ): self {
        return new self((string)$name->value, ColumnType::Bigint, $default, $nullable, $length);
    }

    public static function date(
        BackedEnum $name,
        bool $nullable = true,
        string $default = '1970-01-01 00:00:01',
    ): self {
        return new self((string)$name->value, ColumnType::Date, $default, $nullable);
    }

    public static function decimal(
        BackedEnum $name,
        int $precision,
        int $scale,
        bool $nullable = false,
        ?float $default = 0,
    ): self {
        return new self((string)$name->value, ColumnType::Decimal, $default, $nullable, $precision, $scale);
    }

    public static function int(
        BackedEnum $name,
        int $length,
        bool $nullable = true,
        ?int $default = null,
    ): self {
        return new self((string)$name->value, ColumnType::Int, $default, $nullable, $length);
    }

    public static function mediumblob(
        BackedEnum $name,
        bool $nullable = true,
        ?string $default = null,
    ): self {
        return new self((string)$name->value, ColumnType::Mediumblob, $default, $nullable);
    }

    public static function smallint(
        BackedEnum $name,
        bool $nullable = true,
        ?int $default = null,
    ): self {
        return new self((string)$name->value, ColumnType::SmallInt, $default, $nullable);
    }

    public static function text(
        BackedEnum $name,
        bool $nullable = true,
        ?string $default = null,
    ): self {
        return new self((string)$name->value, ColumnType::Text, $default, $nullable);
    }

    public static function jsonb(
        BackedEnum $name,
        bool $nullable = true,
        ?string $default = null,
    ): self {
        return new self((string)$name->value, ColumnType::Jsonb, $default, $nullable);
    }

    public static function timestamp(
        BackedEnum $name,
        bool $nullable = false,
        ?string $default = '1970-01-01 00:00:01',
    ): self {
        return new self((string)$name->value, ColumnType::Timestamp, $default, $nullable);
    }

    /**
     * Is not supported in PostgreSQL
     */
    public static function tinyint(
        BackedEnum $name,
        int $length = 1,
        bool $nullable = true,
        ?int $default = null,
    ): self {
        return new self((string)$name->value, ColumnType::Tinyint, $default, $nullable, $length);
    }

    public static function uuid(
        BackedEnum $name,
        bool $nullable = false,
    ): self {
        return new self((string)$name->value, ColumnType::Uuid, null, $nullable);
    }

    public static function varchar(
        BackedEnum $name,
        int $length = 255,
        bool $nullable = true,
        ?string $default = null,
    ): self {
        return new self((string)$name->value, ColumnType::Varchar, $default, $nullable, $length);
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
        );
    }
}
