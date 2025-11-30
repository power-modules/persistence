<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Repository;

use DateTimeImmutable;
use Modular\Persistence\Database\Database;
use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Statement\SelectStatement;
use Modular\Persistence\Schema\Adapter\PostgresSchemaQueryGenerator;
use Modular\Persistence\Test\Unit\Repository\Fixture\Employee;
use Modular\Persistence\Test\Unit\Repository\Fixture\Hydrator;
use Modular\Persistence\Test\Unit\Repository\Fixture\Repository;
use Modular\Persistence\Test\Unit\Repository\Fixture\Schema;
use PDO;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class AbstractGenericRepositoryTest extends TestCase
{
    public function test(): void
    {
        $repository = $this->getRepository();
        self::assertCount(0, $repository->getMany());

        $employees = [
            new Employee(Uuid::uuid7()->toString(), 'Dick Aoe', new DateTimeImmutable('2024-12-29 23:21:45'), null),
            new Employee(Uuid::uuid7()->toString(), 'Dick Boe', new DateTimeImmutable('2024-12-29 23:34:43'), null),
            new Employee(Uuid::uuid7()->toString(), 'John Coe', new DateTimeImmutable('2024-12-29 23:45:40'), null),
        ];
        self::assertSame(3, $repository->insertMany($employees));

        $rows = $repository->getMany();
        self::assertCount(3, $rows);
        self::assertSame(3, $repository->getTotal());

        foreach ($rows as $idx => $employee) {
            self::assertSame($employees[$idx]->id, $employee->id);
        }

        $johnZoe = new Employee(Uuid::uuid7()->toString(), 'John Zoe', new DateTimeImmutable('2024-12-29 23:21:45'), null);
        self::assertSame(1, $repository->insertOne($johnZoe));
        self::assertSame('John Zoe', $repository->getOne($johnZoe->id)?->name);

        $filteredRows = $repository->getMany(
            Condition::like(Schema::Name, 'Dick'),
        );
        self::assertCount(2, $filteredRows);
        self::assertSame('Dick Aoe', $filteredRows[0]->name);
        self::assertSame('Dick Boe', $filteredRows[1]->name);

        self::assertSame(1, $repository->deleteMany(
            Condition::like(Schema::Name, 'Dick'),
            Condition::notLike(Schema::Name, 'Boe'),
        ));

        $filteredRowsAfterDeletion = $repository->getMany(
            Condition::like(Schema::Name, 'Dick'),
        );
        self::assertCount(1, $filteredRowsAfterDeletion);
        self::assertSame('Dick Boe', $filteredRowsAfterDeletion[0]->name);

        $johnCoe = $repository->getMany(Condition::equals(Schema::CreatedAt, '2024-12-29 23:45:40'))[0];
        self::assertSame('John Coe', $johnCoe->name);
        self::assertNull($johnCoe->deletedAt);

        $johnCoe = $johnCoe->withDeletedAt('2025-01-01 00:05:30');
        $repository->updateOne($johnCoe);

        $johnCoe = $repository->getOne($johnCoe->id);
        self::assertNotNull($johnCoe);
        self::assertSame('John Coe', $johnCoe->name);
        self::assertSame('2025-01-01 00:05:30', $johnCoe->deletedAt?->format('Y-m-d H:i:s') ?? '');
        self::assertTrue($repository->has(Condition::equals(Schema::Id, $johnCoe->id)));
        self::assertSame(1, $repository->deleteOne($johnCoe->id));
        self::assertFalse($repository->has(Condition::equals(Schema::Id, $johnCoe->id)));

        $employees = [
            new Employee(Uuid::uuid7()->toString(), 'Person 3', new DateTimeImmutable('2024-12-29 23:21:45'), null),
            new Employee(Uuid::uuid7()->toString(), 'Person 4', new DateTimeImmutable('2024-12-29 23:34:43'), null),
            new Employee(Uuid::uuid7()->toString(), 'Person 5', new DateTimeImmutable('2024-12-29 23:45:40'), null),
        ];
        self::assertSame(3, $repository->insertMany($employees));
        self::assertSame(5, $repository->getTotal());

        $selectStatement = new SelectStatement(Schema::getTableName(), [Schema::Name->value]);
        $selectStatement->setStart(3)->setLimit(1);
        $result = $repository->select($selectStatement);
        self::assertCount(1, $result);
        self::assertSame('Person 4', $result[0][Schema::Name->value]);

        // Test findOne
        $person3 = $repository->findOne(Condition::equals(Schema::Name, 'Person 3'));
        self::assertNotNull($person3);
        self::assertSame('Person 3', $person3->name);

        $notFound = $repository->findOne(Condition::equals(Schema::Name, 'Non Existent'));
        self::assertNull($notFound);

        self::assertCount(5, $repository->getMany(Condition::isNull(Schema::DeletedAt)));
        self::assertCount(0, $repository->getMany(Condition::notNull(Schema::DeletedAt)));

        self::assertSame(3, $repository->updateMany([Schema::DeletedAt->value => date('Y-m-d H:i:s')], Condition::like(Schema::Name, 'Person')));
        self::assertCount(3, $repository->getMany(Condition::notNull(Schema::DeletedAt)));
        self::assertSame(3, $repository->updateMany([Schema::DeletedAt->value => null], Condition::like(Schema::Name, 'Person')));
        self::assertCount(5, $repository->getMany(Condition::isNull(Schema::DeletedAt)));

        self::assertSame(3, $repository->deleteMany(Condition::in(Schema::Name, ['Person 3', 'Person 4', 'Person 5'])));
        self::assertCount(1, $repository->getMany(Condition::notIn(Schema::Name, ['Dick Boe'])));
        self::assertCount(1, $repository->getMany(Condition::notIn(Schema::Name, ['John Zoe'])));
    }

    public function testSave(): void
    {
        $repository = $this->getRepository();

        // Test Insert via Save
        $employee = new Employee(Uuid::uuid7()->toString(), 'Save Insert', new DateTimeImmutable(), null);
        self::assertSame(1, $repository->save($employee));

        $savedEmployee = $repository->getOne($employee->id);
        self::assertNotNull($savedEmployee);
        self::assertSame('Save Insert', $savedEmployee->name);

        // Test Update via Save
        $employee = new Employee($employee->id, 'Save Update', $employee->createdAt, $employee->deletedAt);
        self::assertSame(1, $repository->save($employee));

        $updatedEmployee = $repository->getOne($employee->id);
        self::assertNotNull($updatedEmployee);
        self::assertSame('Save Update', $updatedEmployee->name);
        self::assertSame(1, $repository->getTotal());
    }

    public function testUpdateOneNonExistent(): void
    {
        $repository = $this->getRepository();
        $employee = new Employee(Uuid::uuid7()->toString(), 'Non Existent', new DateTimeImmutable(), null);

        self::assertSame(0, $repository->updateOne($employee));
    }

    public function testSaveIdempotency(): void
    {
        $repository = $this->getRepository();
        $employee = new Employee(Uuid::uuid7()->toString(), 'Idempotent', new DateTimeImmutable(), null);

        self::assertSame(1, $repository->save($employee));
        self::assertSame(1, $repository->save($employee));
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
