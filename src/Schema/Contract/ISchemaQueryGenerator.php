<?php

declare(strict_types=1);

namespace Modular\Persistence\Schema\Contract;

use Generator;

interface ISchemaQueryGenerator
{
    /**
     * Returns an array of queries to execute to create a table
     *
     * @return Generator<string>
     */
    public function generate(ISchema $schema, ?string $tableName = null): Generator;

    public function generateAlterAddColumn(ISchema $column): string;
    public function generateAlterChangeColumn(ISchema $column): string;
}
