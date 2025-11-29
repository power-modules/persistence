<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Repository\Fixture;

use Modular\Persistence\Repository\AbstractGenericRepository;

/**
 * @extends AbstractGenericRepository<Employee>
 */
class Repository extends AbstractGenericRepository
{
    protected function getIdFieldName(): string
    {
        return Schema::getPrimaryKey()[0];
    }

    protected function getTableName(): string
    {
        return Schema::getTableName();
    }
}
