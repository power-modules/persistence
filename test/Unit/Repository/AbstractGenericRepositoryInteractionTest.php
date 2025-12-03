<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Repository;

use Modular\Persistence\Database\IDatabase;
use Modular\Persistence\Repository\AbstractGenericRepository;
use Modular\Persistence\Repository\Statement\Factory\GenericStatementFactory;
use Modular\Persistence\Schema\Contract\IHydrator;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class AbstractGenericRepositoryInteractionTest extends TestCase
{
    public function testNullValuesAreBound(): void
    {
        $database = $this->createMock(IDatabase::class);
        $statement = $this->createMock(PDOStatement::class);
        $hydrator = $this->createMock(IHydrator::class);
        $factory = new GenericStatementFactory();

        $repo = new class ($database, $hydrator, $factory) extends AbstractGenericRepository {
            protected function getTableName(): string
            {
                return 'test_table';
            }
        };

        // Setup Hydrator to return data with nulls
        $entity = new \stdClass();
        $hydrator->method('dehydrate')->willReturn([
            'id' => 1,
            'name' => null,
        ]);
        $hydrator->method('getId')->willReturn(null); // Force insert
        $hydrator->method('getIdFieldName')->willReturn('id');

        // Expect prepare to be called
        $database->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        // Expect bindValue to be called exactly twice (once for 'id', once for 'name')
        // If the bug was present (skipping nulls), this would fail as it would be called only once.
        $statement->expects($this->exactly(2))
            ->method('bindValue');

        $statement->method('execute')->willReturn(true);
        $statement->method('rowCount')->willReturn(1);

        $repo->insert($entity);
    }
}
