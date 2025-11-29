<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository;

class RawJoin extends Join
{
    public function __construct(
        JoinType $joinType,
        string $table,
        string $localKey,
        string $foreignKey,
        ?string $alias = null,
    ) {
        parent::__construct(
            $joinType,
            $table,
            $localKey,
            $foreignKey,
            null,
            $alias,
        );
    }
}
