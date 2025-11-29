<?php

declare(strict_types=1);

namespace Modular\Persistence\Schema;

interface IHasIndexes
{
    /**
     * @return array<Index>
     */
    public static function getIndexes(): array;
}
