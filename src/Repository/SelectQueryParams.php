<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository;

final readonly class SelectQueryParams
{
    /**
     * @param array<Condition> $conditions
     * @return void
     */
    public function __construct(
        public array $conditions = [],
        public int $limit = 100,
        public bool $forUpdate = false,
    ) {
    }
}
