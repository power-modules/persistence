<?php

declare(strict_types=1);

namespace Modular\Persistence\Schema\Definition;

use BackedEnum;
use InvalidArgumentException;

final readonly class ForeignKey
{
    public function __construct(
        public string $localColumnName,
        public string $foreignTableName,
        public string $foreignColumnName,
        public ?string $foreignSchemaName = null,
    ) {
        if ($localColumnName === '') {
            throw new InvalidArgumentException('A local column name cannot be empty.');
        }

        if ($foreignTableName === '') {
            throw new InvalidArgumentException('A foreign table name cannot be empty.');
        }

        if ($foreignColumnName === '') {
            throw new InvalidArgumentException('A foreign column name cannot be empty.');
        }

        if ($foreignSchemaName === '') {
            throw new InvalidArgumentException('A foreign schema name cannot be empty when provided.');
        }
    }

    public static function make(
        BackedEnum $localColumnName,
        string $foreignTableName,
        BackedEnum $foreignColumnName,
        ?string $foreignSchemaName = null,
    ): self {
        return new self(
            (string)$localColumnName->value,
            $foreignTableName,
            (string)$foreignColumnName->value,
            $foreignSchemaName,
        );
    }
}
