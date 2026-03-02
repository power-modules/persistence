<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Integration\Repository;

use DateTimeImmutable;
use Modular\Persistence\Repository\AbstractGenericRepository;
use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Exception\EntityNotFoundException;
use Modular\Persistence\Repository\Statement\SelectStatement;
use Modular\Persistence\Tests\Integration\Fixture\Employee;
use Modular\Persistence\Tests\Integration\Fixture\EmployeeHydrator;
use Modular\Persistence\Tests\Integration\Fixture\EmployeeRepository;
use Modular\Persistence\Tests\Integration\Fixture\EmployeeSchema;
use Modular\Persistence\Tests\Integration\Support\PostgresTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Ramsey\Uuid\Uuid;

/**
 * Full CRUD lifecycle tests for AbstractGenericRepository against real PostgreSQL.
 */
#[CoversClass(AbstractGenericRepository::class)]
final class RepositoryCrudTest extends PostgresTestCase
{
    protected static function getSchemas(): array
    {
        return [EmployeeSchema::Id];
    }

    private function getRepository(): EmployeeRepository
    {
        return new EmployeeRepository(
            static::getConnection(),
            new EmployeeHydrator(),
        );
    }

    // ── Insert / Find ────────────────────────────────────────────────

    public function testInsertAndFind(): void
    {
        $repo = $this->getRepository();
        $employee = new Employee(Uuid::uuid7()->toString(), 'Alice', new DateTimeImmutable(), null);

        self::assertSame(1, $repo->insert($employee));

        $found = $repo->find($employee->id);
        self::assertNotNull($found);
        self::assertSame('Alice', $found->name);
        self::assertSame($employee->id, $found->id);
    }

    public function testInsertAllAndFindBy(): void
    {
        $repo = $this->getRepository();
        $employees = [
            new Employee(Uuid::uuid7()->toString(), 'Dick Aoe', new DateTimeImmutable('2024-12-29 23:21:45'), null),
            new Employee(Uuid::uuid7()->toString(), 'Dick Boe', new DateTimeImmutable('2024-12-29 23:34:43'), null),
            new Employee(Uuid::uuid7()->toString(), 'John Coe', new DateTimeImmutable('2024-12-29 23:45:40'), null),
        ];

        self::assertSame(3, $repo->insertAll($employees));

        $rows = $repo->findBy();
        self::assertCount(3, $rows);
        self::assertSame(3, $repo->count());

        foreach ($rows as $idx => $employee) {
            self::assertSame($employees[$idx]->id, $employee->id);
        }
    }

    public function testInsertAllWithCustomChunkSize(): void
    {
        $repo = $this->getRepository();
        $employees = [];
        for ($i = 0; $i < 5; $i++) {
            $employees[] = new Employee(Uuid::uuid7()->toString(), 'Chunk ' . $i, new DateTimeImmutable(), null);
        }

        self::assertSame(5, $repo->insertAll($employees, chunkSize: 2));
        self::assertSame(5, $repo->count());
    }

    public function testInsertAllLargeBatch(): void
    {
        $repo = $this->getRepository();
        $employees = [];
        for ($i = 0; $i < 550; $i++) {
            $employees[] = new Employee(Uuid::uuid7()->toString(), 'Person ' . $i, new DateTimeImmutable(), null);
        }

        $repo->insertAll($employees);
        self::assertCount(550, $repo->findBy());
    }

    // ── FindBy with conditions ───────────────────────────────────────

    public function testFindByWithConditions(): void
    {
        $repo = $this->getRepository();
        $repo->insertAll([
            new Employee(Uuid::uuid7()->toString(), 'Dick Aoe', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'Dick Boe', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'John Coe', new DateTimeImmutable(), null),
        ]);

        $filtered = $repo->findBy([Condition::like(EmployeeSchema::Name, 'Dick')]);
        self::assertCount(2, $filtered);
        self::assertSame('Dick Aoe', $filtered[0]->name);
        self::assertSame('Dick Boe', $filtered[1]->name);
    }

    public function testFindByWithPagination(): void
    {
        $repo = $this->getRepository();
        $employees = [];
        for ($i = 0; $i < 20; $i++) {
            $employees[] = new Employee(Uuid::uuid7()->toString(), sprintf('Person %02d', $i), new DateTimeImmutable(), null);
        }
        $repo->insertAll($employees);

        $page1 = $repo->findBy(limit: 5);
        self::assertCount(5, $page1);

        $page2 = $repo->findBy(limit: 5, offset: 5);
        self::assertCount(5, $page2);
        self::assertNotSame($page1[0]->id, $page2[0]->id);

        $all = $repo->findBy();
        self::assertCount(20, $all);
    }

    public function testFindByWithInCondition(): void
    {
        $repo = $this->getRepository();
        $repo->insertAll([
            new Employee(Uuid::uuid7()->toString(), 'Person A', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'Person B', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'Person C', new DateTimeImmutable(), null),
        ]);

        self::assertCount(2, $repo->findBy([Condition::in(EmployeeSchema::Name, ['Person A', 'Person B'])]));
        self::assertCount(1, $repo->findBy([Condition::notIn(EmployeeSchema::Name, ['Person A', 'Person B'])]));
    }

    public function testFindByWithNullConditions(): void
    {
        $repo = $this->getRepository();
        $repo->insertAll([
            new Employee(Uuid::uuid7()->toString(), 'Active', new DateTimeImmutable(), null),
            (new Employee(Uuid::uuid7()->toString(), 'Deleted', new DateTimeImmutable(), null))->withDeletedAt(),
        ]);

        self::assertCount(1, $repo->findBy([Condition::isNull(EmployeeSchema::DeletedAt)]));
        self::assertCount(1, $repo->findBy([Condition::notNull(EmployeeSchema::DeletedAt)]));
    }

    public function testComparisonOperators(): void
    {
        $repo = $this->getRepository();
        $repo->insertAll([
            new Employee(Uuid::uuid7()->toString(), 'A', new DateTimeImmutable('2024-01-01'), null),
            new Employee(Uuid::uuid7()->toString(), 'B', new DateTimeImmutable('2024-06-01'), null),
            new Employee(Uuid::uuid7()->toString(), 'C', new DateTimeImmutable('2024-12-01'), null),
        ]);

        self::assertCount(2, $repo->findBy([Condition::greaterEquals(EmployeeSchema::CreatedAt, '2024-06-01')]));
        self::assertCount(1, $repo->findBy([Condition::greater(EmployeeSchema::CreatedAt, '2024-06-01')]));
        self::assertCount(2, $repo->findBy([Condition::lessEquals(EmployeeSchema::CreatedAt, '2024-06-01')]));
        self::assertCount(1, $repo->findBy([Condition::less(EmployeeSchema::CreatedAt, '2024-06-01')]));
    }

    // ── FindOneBy / FindOrFail ───────────────────────────────────────

    public function testFindOneBy(): void
    {
        $repo = $this->getRepository();
        $employee = new Employee(Uuid::uuid7()->toString(), 'Unique Person', new DateTimeImmutable(), null);
        $repo->insert($employee);

        $found = $repo->findOneBy([Condition::equals(EmployeeSchema::Name, 'Unique Person')]);
        self::assertNotNull($found);
        self::assertSame('Unique Person', $found->name);

        $notFound = $repo->findOneBy([Condition::equals(EmployeeSchema::Name, 'Nobody')]);
        self::assertNull($notFound);
    }

    public function testFindOneByOrFail(): void
    {
        $repo = $this->getRepository();
        $employee = new Employee(Uuid::uuid7()->toString(), 'Findable One', new DateTimeImmutable(), null);
        $repo->insert($employee);

        $found = $repo->findOneByOrFail([Condition::equals(EmployeeSchema::Name, 'Findable One')]);
        self::assertSame('Findable One', $found->name);
    }

    public function testFindOneByOrFailThrowsOnMissing(): void
    {
        $repo = $this->getRepository();

        $this->expectException(EntityNotFoundException::class);
        $repo->findOneByOrFail([Condition::equals(EmployeeSchema::Name, 'Nobody')]);
    }

    public function testFindOrFail(): void
    {
        $repo = $this->getRepository();
        $employee = new Employee(Uuid::uuid7()->toString(), 'Findable', new DateTimeImmutable(), null);
        $repo->insert($employee);

        $found = $repo->findOrFail($employee->id);
        self::assertSame('Findable', $found->name);
    }

    public function testFindOrFailThrowsOnMissing(): void
    {
        $repo = $this->getRepository();

        $this->expectException(EntityNotFoundException::class);
        $repo->findOrFail('non-existent-id');
    }

    // ── Update ───────────────────────────────────────────────────────

    public function testUpdate(): void
    {
        $repo = $this->getRepository();
        $employee = new Employee(Uuid::uuid7()->toString(), 'John Coe', new DateTimeImmutable('2024-12-29 23:45:40'), null);
        $repo->insert($employee);

        $updated = $employee->withDeletedAt('2025-01-01 00:05:30');
        $repo->update($updated);

        $found = $repo->find($employee->id);
        self::assertNotNull($found);
        self::assertSame('John Coe', $found->name);
        self::assertSame('2025-01-01 00:05:30', $found->deletedAt?->format('Y-m-d H:i:s'));
    }

    public function testUpdateBy(): void
    {
        $repo = $this->getRepository();
        $repo->insertAll([
            new Employee(Uuid::uuid7()->toString(), 'Person 1', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'Person 2', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'Other', new DateTimeImmutable(), null),
        ]);

        $now = date('Y-m-d H:i:sP');
        self::assertSame(2, $repo->updateBy(
            [EmployeeSchema::DeletedAt->value => $now],
            [Condition::like(EmployeeSchema::Name, 'Person')],
        ));

        self::assertCount(2, $repo->findBy([Condition::notNull(EmployeeSchema::DeletedAt)]));
        self::assertCount(1, $repo->findBy([Condition::isNull(EmployeeSchema::DeletedAt)]));

        // Verify setting back to null works
        self::assertSame(2, $repo->updateBy(
            [EmployeeSchema::DeletedAt->value => null],
            [Condition::like(EmployeeSchema::Name, 'Person')],
        ));
        self::assertCount(3, $repo->findBy([Condition::isNull(EmployeeSchema::DeletedAt)]));
    }

    public function testUpdateNonExistent(): void
    {
        $repo = $this->getRepository();
        $employee = new Employee(Uuid::uuid7()->toString(), 'Non Existent', new DateTimeImmutable(), null);

        self::assertSame(0, $repo->update($employee));
    }

    // ── Delete ───────────────────────────────────────────────────────

    public function testDeleteById(): void
    {
        $repo = $this->getRepository();
        $employee = new Employee(Uuid::uuid7()->toString(), 'Deletable', new DateTimeImmutable(), null);
        $repo->insert($employee);

        self::assertTrue($repo->exists([Condition::equals(EmployeeSchema::Id, $employee->id)]));
        self::assertSame(1, $repo->delete($employee->id));
        self::assertFalse($repo->exists([Condition::equals(EmployeeSchema::Id, $employee->id)]));
    }

    public function testDeleteBy(): void
    {
        $repo = $this->getRepository();
        $repo->insertAll([
            new Employee(Uuid::uuid7()->toString(), 'Dick Aoe', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'Dick Boe', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'John Coe', new DateTimeImmutable(), null),
        ]);

        self::assertSame(1, $repo->deleteBy([
            Condition::like(EmployeeSchema::Name, 'Dick'),
            Condition::notLike(EmployeeSchema::Name, 'Boe'),
        ]));

        $remaining = $repo->findBy([Condition::like(EmployeeSchema::Name, 'Dick')]);
        self::assertCount(1, $remaining);
        self::assertSame('Dick Boe', $remaining[0]->name);
    }

    // ── Upsert ───────────────────────────────────────────────────────

    public function testUpsertInsert(): void
    {
        $repo = $this->getRepository();
        $employee = new Employee(Uuid::uuid7()->toString(), 'Upsert Insert', new DateTimeImmutable(), null);

        self::assertSame(1, $repo->upsert($employee));

        $found = $repo->find($employee->id);
        self::assertNotNull($found);
        self::assertSame('Upsert Insert', $found->name);
    }

    public function testUpsertUpdate(): void
    {
        $repo = $this->getRepository();
        $employee = new Employee(Uuid::uuid7()->toString(), 'Original', new DateTimeImmutable(), null);
        $repo->upsert($employee);

        $updated = $employee->withName('Updated');
        $repo->upsert($updated);

        $found = $repo->find($employee->id);
        self::assertNotNull($found);
        self::assertSame('Updated', $found->name);
        self::assertSame(1, $repo->count());
    }

    public function testUpsertIdempotency(): void
    {
        $repo = $this->getRepository();
        $employee = new Employee(Uuid::uuid7()->toString(), 'Idempotent', new DateTimeImmutable(), null);

        self::assertSame(1, $repo->upsert($employee));
        self::assertSame(1, $repo->upsert($employee));
        self::assertSame(1, $repo->count());
    }

    // ── Exists / Count ───────────────────────────────────────────────

    public function testExistsAndCount(): void
    {
        $repo = $this->getRepository();
        self::assertFalse($repo->exists());
        self::assertSame(0, $repo->count());

        $repo->insert(new Employee(Uuid::uuid7()->toString(), 'One', new DateTimeImmutable(), null));
        self::assertTrue($repo->exists());
        self::assertSame(1, $repo->count());
    }

    // ── Raw select ───────────────────────────────────────────────────

    public function testSelectRaw(): void
    {
        $repo = $this->getRepository();
        $repo->insertAll([
            new Employee(Uuid::uuid7()->toString(), 'Person 1', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'Person 2', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'Person 3', new DateTimeImmutable(), null),
        ]);

        $selectStatement = new SelectStatement(EmployeeSchema::getTableName(), [EmployeeSchema::Name->value]);
        $selectStatement->setStart(1)->setLimit(1);
        $result = $repo->select($selectStatement);
        self::assertCount(1, $result);
    }

    // ── Deprecated save() ────────────────────────────────────────────

    #[IgnoreDeprecations]
    public function testSaveInsertThenUpdate(): void
    {
        $repo = $this->getRepository();

        $employee = new Employee(Uuid::uuid7()->toString(), 'Save Insert', new DateTimeImmutable(), null);
        self::assertSame(1, $repo->save($employee));

        $found = $repo->find($employee->id);
        self::assertNotNull($found);
        self::assertSame('Save Insert', $found->name);

        $updated = $employee->withName('Save Update');
        self::assertSame(1, $repo->save($updated));

        $found = $repo->find($employee->id);
        self::assertNotNull($found);
        self::assertSame('Save Update', $found->name);
        self::assertSame(1, $repo->count());
    }
}
