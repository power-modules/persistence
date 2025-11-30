<?php

declare(strict_types=1);

namespace Modular\Persistence\Schema\Contract;

use Modular\Persistence\Schema\Definition\Index;

interface IHasIndexes
{
    /**
     * @return array<Index>
     */
    public static function getIndexes(): array;
}
