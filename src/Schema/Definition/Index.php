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
    ): self {
        $columnNames = array_map(
            static fn (BackedEnum $col): string => (string)$col->value,
            $columns,
        );

        return new self($columnNames, $name, $unique);
    }

    public function makeName(string $tableName): string
    {
        if ($tableName === '') {
            return sprintf('idx_%s', crc32(implode('.', $this->columns)));
        }

        return sprintf('idx_%s_%s', $tableName, crc32(implode('.', $this->columns)));
    }
}
