<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Integration\Fixture;

use Modular\Persistence\Repository\AbstractGenericRepository;

/**
 * @extends AbstractGenericRepository<Employee>
 */
class EmployeeRepository extends AbstractGenericRepository
{
    protected function getTableName(): string
    {
        return EmployeeSchema::getTableName();
    }
}
