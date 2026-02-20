<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Repository;

use DateTimeImmutable;
use Modular\Persistence\Database\Database;
use Modular\Persistence\Repository\AbstractGenericRepository;
use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Exception\EntityNotFoundException;
use Modular\Persistence\Repository\Statement\SelectStatement;
use Modular\Persistence\Schema\Adapter\PostgresSchemaQueryGenerator;
use Modular\Persistence\Test\Unit\Repository\Fixture\Employee;
use Modular\Persistence\Test\Unit\Repository\Fixture\Hydrator;
use Modular\Persistence\Test\Unit\Repository\Fixture\Repository;
use Modular\Persistence\Test\Unit\Repository\Fixture\Schema;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

#[CoversClass(AbstractGenericRepository::class)]
class AbstractGenericRepositoryTest extends TestCase
{
    public function test(): void
    {
        $repository = $this->getRepository();
        self::assertCount(0, $repository->findBy());

        $employees = [
            new Employee(Uuid::uuid7()->toString(), 'Dick Aoe', new DateTimeImmutable('2024-12-29 23:21:45'), null),
            new Employee(Uuid::uuid7()->toString(), 'Dick Boe', new DateTimeImmutable('2024-12-29 23:34:43'), null),
            new Employee(Uuid::uuid7()->toString(), 'John Coe', new DateTimeImmutable('2024-12-29 23:45:40'), null),
        ];
        self::assertSame(3, $repository->insertAll($employees));

        $rows = $repository->findBy();
        self::assertCount(3, $rows);
        self::assertSame(3, $repository->count());

        foreach ($rows as $idx => $employee) {
            self::assertSame($employees[$idx]->id, $employee->id);
        }

        $johnZoe = new Employee(Uuid::uuid7()->toString(), 'John Zoe', new DateTimeImmutable('2024-12-29 23:21:45'), null);
        self::assertSame(1, $repository->insert($johnZoe));
        self::assertSame('John Zoe', $repository->find($johnZoe->id)?->name);

        $filteredRows = $repository->findBy(
            [Condition::like(Schema::Name, 'Dick')],
        );
        self::assertCount(2, $filteredRows);
        self::assertSame('Dick Aoe', $filteredRows[0]->name);
        self::assertSame('Dick Boe', $filteredRows[1]->name);

        self::assertSame(1, $repository->deleteBy([
            Condition::like(Schema::Name, 'Dick'),
            Condition::notLike(Schema::Name, 'Boe'),
        ]));

        $filteredRowsAfterDeletion = $repository->findBy(
            [Condition::like(Schema::Name, 'Dick')],
        );
        self::assertCount(1, $filteredRowsAfterDeletion);
        self::assertSame('Dick Boe', $filteredRowsAfterDeletion[0]->name);

        $johnCoe = $repository->findBy([Condition::equals(Schema::CreatedAt, '2024-12-29 23:45:40')])[0];
        self::assertSame('John Coe', $johnCoe->name);
        self::assertNull($johnCoe->deletedAt);

        $johnCoe = $johnCoe->withDeletedAt('2025-01-01 00:05:30');
        $repository->update($johnCoe);

        $johnCoe = $repository->find($johnCoe->id);
        self::assertNotNull($johnCoe);
        self::assertSame('John Coe', $johnCoe->name);
        self::assertSame('2025-01-01 00:05:30', $johnCoe->deletedAt?->format('Y-m-d H:i:s') ?? '');
        self::assertTrue($repository->exists([Condition::equals(Schema::Id, $johnCoe->id)]));
        self::assertSame(1, $repository->delete($johnCoe->id));
        self::assertFalse($repository->exists([Condition::equals(Schema::Id, $johnCoe->id)]));

        $employees = [
            new Employee(Uuid::uuid7()->toString(), 'Person 3', new DateTimeImmutable('2024-12-29 23:21:45'), null),
            new Employee(Uuid::uuid7()->toString(), 'Person 4', new DateTimeImmutable('2024-12-29 23:34:43'), null),
            new Employee(Uuid::uuid7()->toString(), 'Person 5', new DateTimeImmutable('2024-12-29 23:45:40'), null),
        ];
        self::assertSame(3, $repository->insertAll($employees));
        self::assertSame(5, $repository->count());

        $selectStatement = new SelectStatement(Schema::getTableName(), [Schema::Name->value]);
        $selectStatement->setStart(3)->setLimit(1);
        $result = $repository->select($selectStatement);
        self::assertCount(1, $result);
        self::assertSame('Person 4', $result[0][Schema::Name->value]);

        // Test findOne
        $person3 = $repository->findOneBy([Condition::equals(Schema::Name, 'Person 3')]);
        self::assertNotNull($person3);
        self::assertSame('Person 3', $person3->name);

        $notFound = $repository->findOneBy([Condition::equals(Schema::Name, 'Non Existent')]);
        self::assertNull($notFound);

        self::assertCount(5, $repository->findBy([Condition::isNull(Schema::DeletedAt)]));
        self::assertCount(0, $repository->findBy([Condition::notNull(Schema::DeletedAt)]));

        self::assertSame(3, $repository->updateBy([Schema::DeletedAt->value => date('Y-m-d H:i:s')], [Condition::like(Schema::Name, 'Person')]));
        self::assertCount(3, $repository->findBy([Condition::notNull(Schema::DeletedAt)]));
        self::assertSame(3, $repository->updateBy([Schema::DeletedAt->value => null], [Condition::like(Schema::Name, 'Person')]));
        self::assertCount(5, $repository->findBy([Condition::isNull(Schema::DeletedAt)]));

        self::assertSame(3, $repository->deleteBy([Condition::in(Schema::Name, ['Person 3', 'Person 4', 'Person 5'])]));
        self::assertCount(1, $repository->findBy([Condition::notIn(Schema::Name, ['Dick Boe'])]));
        self::assertCount(1, $repository->findBy([Condition::notIn(Schema::Name, ['John Zoe'])]));
    }

    public function testSave(): void
    {
        $repository = $this->getRepository();

        // Test Insert via Save
        $employee = new Employee(Uuid::uuid7()->toString(), 'Save Insert', new DateTimeImmutable(), null);
        self::assertSame(1, $repository->save($employee));

        $savedEmployee = $repository->find($employee->id);
        self::assertNotNull($savedEmployee);
        self::assertSame('Save Insert', $savedEmployee->name);

        // Test Update via Save
        $employee = new Employee($employee->id, 'Save Update', $employee->createdAt, $employee->deletedAt);
        self::assertSame(1, $repository->save($employee));

        $updatedEmployee = $repository->find($employee->id);
        self::assertNotNull($updatedEmployee);
        self::assertSame('Save Update', $updatedEmployee->name);
        self::assertSame(1, $repository->count());
    }

    public function testUpdateOneNonExistent(): void
    {
        $repository = $this->getRepository();
        $employee = new Employee(Uuid::uuid7()->toString(), 'Non Existent', new DateTimeImmutable(), null);

        self::assertSame(0, $repository->update($employee));
    }

    public function testSaveIdempotency(): void
    {
        $repository = $this->getRepository();
        $employee = new Employee(Uuid::uuid7()->toString(), 'Idempotent', new DateTimeImmutable(), null);

        self::assertSame(1, $repository->save($employee));
        self::assertSame(1, $repository->save($employee));
    }

    public function testFindByReturnsAllRecords(): void
    {
        $repository = $this->getRepository();
        $employees = [];
        for ($i = 0; $i < 550; $i++) {
            $employees[] = new Employee(Uuid::uuid7()->toString(), 'Person ' . $i, new DateTimeImmutable(), null);
        }

        $repository->insertAll($employees);

        self::assertCount(550, $repository->findBy());
    }

    public function testUpsert(): void
    {
        $repository = $this->getRepository();

        // Upsert as insert
        $employee = new Employee(Uuid::uuid7()->toString(), 'Upsert Insert', new DateTimeImmutable(), null);
        self::assertSame(1, $repository->upsert($employee));

        $found = $repository->find($employee->id);
        self::assertNotNull($found);
        self::assertSame('Upsert Insert', $found->name);

        // Upsert as update
        $updated = new Employee($employee->id, 'Upsert Update', $employee->createdAt, $employee->deletedAt);
        self::assertSame(1, $repository->upsert($updated));

        $found = $repository->find($employee->id);
        self::assertNotNull($found);
        self::assertSame('Upsert Update', $found->name);
        self::assertSame(1, $repository->count());
    }

    public function testUpsertIdempotency(): void
    {
        $repository = $this->getRepository();
        $employee = new Employee(Uuid::uuid7()->toString(), 'Idempotent Upsert', new DateTimeImmutable(), null);

        self::assertSame(1, $repository->upsert($employee));
        self::assertSame(1, $repository->upsert($employee));
        self::assertSame(1, $repository->count());
    }

    public function testFindOrFail(): void
    {
        $repository = $this->getRepository();
        $employee = new Employee(Uuid::uuid7()->toString(), 'Findable', new DateTimeImmutable(), null);
        $repository->insert($employee);

        $found = $repository->findOrFail($employee->id);
        self::assertSame('Findable', $found->name);
    }

    public function testFindOrFailThrowsOnMissing(): void
    {
        $repository = $this->getRepository();

        $this->expectException(EntityNotFoundException::class);
        $repository->findOrFail('non-existent-id');
    }

    public function testFindOneByOrFail(): void
    {
        $repository = $this->getRepository();
        $employee = new Employee(Uuid::uuid7()->toString(), 'Unique Person', new DateTimeImmutable(), null);
        $repository->insert($employee);

        $found = $repository->findOneByOrFail([Condition::equals(Schema::Name, 'Unique Person')]);
        self::assertSame('Unique Person', $found->name);
    }

    public function testFindOneByOrFailThrowsOnMissing(): void
    {
        $repository = $this->getRepository();

        $this->expectException(EntityNotFoundException::class);
        $repository->findOneByOrFail([Condition::equals(Schema::Name, 'Nobody')]);
    }

    public function testInsertAllWithCustomChunkSize(): void
    {
        $repository = $this->getRepository();
        $employees = [];
        for ($i = 0; $i < 5; $i++) {
            $employees[] = new Employee(Uuid::uuid7()->toString(), 'Chunk ' . $i, new DateTimeImmutable(), null);
        }

        self::assertSame(5, $repository->insertAll($employees, chunkSize: 2));
        self::assertSame(5, $repository->count());
    }

    public function testFindByWithPagination(): void
    {
        $repository = $this->getRepository();
        $employees = [];
        for ($i = 0; $i < 20; $i++) {
            $employees[] = new Employee(Uuid::uuid7()->toString(), sprintf('Person %02d', $i), new DateTimeImmutable(), null);
        }
        $repository->insertAll($employees);

        // Limit only
        $page = $repository->findBy(limit: 5);
        self::assertCount(5, $page);

        // Limit + offset
        $page2 = $repository->findBy(limit: 5, offset: 5);
        self::assertCount(5, $page2);
        self::assertNotSame($page[0]->id, $page2[0]->id);

        // No limit returns all
        $all = $repository->findBy();
        self::assertCount(20, $all);
    }

    private function getRepository(): Repository
    {
        $pdo = new PDO('sqlite::memory:');
        $schemaQueryGenerator = new PostgresSchemaQueryGenerator();

        foreach ($schemaQueryGenerator->generate(Schema::Id) as $query) {
            $query = str_replace(' AUTO_INCREMENT', '', $query);
            $pdo->query($query);
        }

        return new Repository(
            new Database($pdo),
            new Hydrator(),
        );
    }
}
