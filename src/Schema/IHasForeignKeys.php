<?php

declare(strict_types=1);

namespace Modular\Persistence\Schema;

interface IHasForeignKeys
{
    /**
     * @return array<ForeignKey>
     */
    public static function getForeignKeys(): array;
}
