<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\TestUser;
use App\Hydrator\TestUserHydrator;
use Modular\Persistence\Database\IDatabase;
use Modular\Persistence\Repository\AbstractGenericRepository;
use Modular\Persistence\Test\Unit\Console\Fixtures\TestUserSchema;

/**
 * @extends AbstractGenericRepository<TestUser>
 */
class TestUserRepository extends AbstractGenericRepository
{
    public function __construct(IDatabase $database, TestUserHydrator $hydrator)
    {
        parent::__construct($database, $hydrator);
    }

    protected function getTableName(): string
    {
        return TestUserSchema::getTableName();
    }
}
