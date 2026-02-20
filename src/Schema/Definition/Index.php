<?php

declare(strict_types=1);

namespace Modular\Persistence\Schema\Definition;

use BackedEnum;
use InvalidArgumentException;

final readonly class Index
{
    /**
     * @param array<string> $columns
     */
    public function __construct(
        public array $columns,
        public ?string $name,
        public bool $isUnique,
        public IndexType $type = IndexType::Btree,
        public bool $isExpression = false,
    ) {
        if (count($columns) === 0) {
            throw new InvalidArgumentException('Column list cannot be empty.');
        }

        if ($name === '') {
            throw new InvalidArgumentException('An index name cannot be empty.');
        }
    }

    /**
     * @param array<BackedEnum> $columns
     */
    public static function make(
        array $columns,
        bool $unique = false,
        ?string $name = null,
        IndexType $type = IndexType::Btree,
    ): self {
        $columnNames = array_map(
            static fn (BackedEnum $col): string => (string)$col->value,
            $columns,
        );

        return new self($columnNames, $name, $unique, $type);
    }

    /**
     * Create an expression-based index (e.g. for JSONB path expressions).
     *
     * The expression is stored verbatim and NOT quoted as an identifier.
     * The caller is responsible for quoting column names within the expression.
     *
     * Example: Index::expression("(\"metadata\"->>'keywords')", IndexType::Gin)
     */
    public static function expression(
        string $expression,
        IndexType $type = IndexType::Gin,
        bool $unique = false,
        ?string $name = null,
    ): self {
        return new self([$expression], $name, $unique, $type, isExpression: true);
    }

    public function makeName(string $tableName): string
    {
        $hashInput = implode('.', $this->columns);

        if ($this->type !== IndexType::Btree) {
            $hashInput .= '.' . $this->type->name;
        }

        if ($tableName === '') {
            return sprintf('idx_%s', crc32($hashInput));
        }

        return sprintf('idx_%s_%s', $tableName, crc32($hashInput));
    }
}
