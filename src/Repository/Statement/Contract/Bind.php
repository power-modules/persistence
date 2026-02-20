<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement\Contract;

use InvalidArgumentException;
use PDO;
use RuntimeException;

readonly class Bind
{
    public function __construct(
        public string $column,
        public string $name,
        public mixed $value,
        public int $type,
    ) {
        if ($value !== null && is_scalar($value) === false) {
            throw new InvalidArgumentException(
                sprintf('Bind value should be of scalar type. "%s" given (%s)', gettype($value), $column),
            );
        }
    }

    public static function create(string $column, string $name, mixed $value): self
    {
        $type = match (gettype($value)) {
            'boolean' => PDO::PARAM_BOOL,
            'integer' => PDO::PARAM_INT,
            'double', 'string' => PDO::PARAM_STR,
            'NULL' => PDO::PARAM_NULL,
            default => throw new RuntimeException(sprintf('Unknown data type: %s', gettype($value))),
        };

        return new self($column, $name, $value, $type);
    }

    /**
     * Create a bind for JSON/JSONB values.
     *
     * If the value is an array, it is automatically json_encode()'d.
     * String values are passed through as-is (assumed to be pre-encoded JSON).
     *
     * @param array<mixed>|string $value
     */
    public static function json(string $column, string $name, array|string $value): self
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_THROW_ON_ERROR);
        }

        return new self($column, $name, $value, PDO::PARAM_STR);
    }
}
