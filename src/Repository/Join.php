<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository;

class Join
{
    public private(set) string $localKey {
        set(\BackedEnum|string $value) => $value instanceof \BackedEnum ? (string) $value->value : $value;
    }

    public private(set) string $foreignKey {
        set(\BackedEnum|string $value) => $value instanceof \BackedEnum ? (string) $value->value : $value;
    }

    public function __construct(
        public readonly JoinType $joinType,
        public readonly string $table,
        \BackedEnum|string $localKey,
        \BackedEnum|string $foreignKey,
        public readonly ?string $localTable = null,
        public readonly ?string $alias = null,
        public readonly ?string $localKeyType = null,
    ) {
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;
    }
}
