<?php

declare(strict_types=1);

namespace Modular\Persistence\Schema\Contract;

use Modular\Persistence\Schema\Definition\ForeignKey;

interface IHasForeignKeys
{
    /**
     * @return array<ForeignKey>
     */
    public static function getForeignKeys(): array;
}
