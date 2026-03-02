<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Integration\Repository;

use DateTimeImmutable;
use Modular\Persistence\Repository\AbstractGenericRepository;
use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Test\Integration\Fixture\Employee;
use Modular\Persistence\Test\Integration\Fixture\EmployeeHydrator;
use Modular\Persistence\Test\Integration\Fixture\EmployeeRepository;
use Modular\Persistence\Test\Integration\Fixture\EmployeeSchema;
use Modular\Persistence\Test\Integration\Support\PostgresTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Ramsey\Uuid\Uuid;

/**
 * Integration tests for PostgreSQL ILIKE operator.
 *
 * ILIKE is truly case-insensitive for all characters, unlike SQLite's LIKE which
 * is only case-insensitive for ASCII letters.
 */
#[CoversClass(AbstractGenericRepository::class)]
class IlikeQueryTest extends PostgresTestCase
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

    public function testIlikeCaseInsensitiveSearch(): void
    {
        $repo = $this->getRepository();
        $repo->insertAll([
            new Employee(Uuid::uuid7()->toString(), 'John Smith', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'JOHN DOE', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'john connor', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'Jane Doe', new DateTimeImmutable(), null),
        ]);

        $results = $repo->findBy([Condition::ilike(EmployeeSchema::Name, 'john')]);

        self::assertCount(3, $results);
    }

    public function testNotIlike(): void
    {
        $repo = $this->getRepository();
        $repo->insertAll([
            new Employee(Uuid::uuid7()->toString(), 'John Smith', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'JOHN DOE', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'Jane Doe', new DateTimeImmutable(), null),
        ]);

        $results = $repo->findBy([Condition::notIlike(EmployeeSchema::Name, 'john')]);

        self::assertCount(1, $results);
        self::assertSame('Jane Doe', $results[0]->name);
    }

    public function testIlikeWithUnicode(): void
    {
        $repo = $this->getRepository();
        $repo->insertAll([
            new Employee(Uuid::uuid7()->toString(), 'André Müller', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'ANDRÉ MÜLLER', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'Bob Jones', new DateTimeImmutable(), null),
        ]);

        $results = $repo->findBy([Condition::ilike(EmployeeSchema::Name, 'andré')]);

        self::assertCount(2, $results);
    }

    public function testIlikeVsLikeBehavior(): void
    {
        $repo = $this->getRepository();
        $repo->insertAll([
            new Employee(Uuid::uuid7()->toString(), 'UPPERCASE', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'lowercase', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'MiXeDcAsE', new DateTimeImmutable(), null),
        ]);

        // LIKE is case-sensitive in PostgreSQL — lowercase pattern does NOT match UPPERCASE data
        $likeResults = $repo->findBy([Condition::like(EmployeeSchema::Name, 'uppercase')]);
        self::assertCount(0, $likeResults);

        // LIKE with correct case DOES match
        $likeExact = $repo->findBy([Condition::like(EmployeeSchema::Name, 'UPPERCASE')]);
        self::assertCount(1, $likeExact);
        self::assertSame('UPPERCASE', $likeExact[0]->name);

        // ILIKE is case-insensitive
        $ilikeResults = $repo->findBy([Condition::ilike(EmployeeSchema::Name, 'uppercase')]);
        self::assertCount(1, $ilikeResults);

        // Search for mixed case pattern
        $ilikeResults = $repo->findBy([Condition::ilike(EmployeeSchema::Name, 'mixedcase')]);
        self::assertCount(1, $ilikeResults);
    }
}
