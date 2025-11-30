<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement\Contract;

use InvalidArgumentException;

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
}
