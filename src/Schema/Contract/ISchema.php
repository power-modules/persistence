<?php

declare(strict_types=1);

namespace Modular\Persistence\Schema\Contract;

use Modular\Persistence\Schema\Definition\ColumnDefinition;
use ValueError;

interface ISchema
{
    public static function getTableName(): string;

    /**
     * @return array<self>
     */
    public static function cases(): array;

    /**
     * Returns schema member (column) by its name
     *
     * @throws ValueError
     */
    public static function from(int|string $value): static;

    public function getColumnDefinition(): ColumnDefinition;
}
