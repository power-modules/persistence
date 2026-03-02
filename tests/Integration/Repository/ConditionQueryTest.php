<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Integration\Repository;

use DateTimeImmutable;
use Modular\Persistence\Repository\AbstractGenericRepository;
use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Tests\Integration\Fixture\Employee;
use Modular\Persistence\Tests\Integration\Fixture\EmployeeHydrator;
use Modular\Persistence\Tests\Integration\Fixture\EmployeeRepository;
use Modular\Persistence\Tests\Integration\Fixture\EmployeeSchema;
use Modular\Persistence\Tests\Integration\Support\PostgresTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Ramsey\Uuid\Uuid;

/**
 * Integration tests for condition-based queries: ILIKE, NOT ILIKE, LIKE vs ILIKE,
 * Unicode handling, and other condition operators against real PostgreSQL.
 *
 * Merged from old IlikeQueryTest + expanded condition coverage.
 */
#[CoversClass(AbstractGenericRepository::class)]
final class ConditionQueryTest extends PostgresTestCase
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

    // ── ILIKE ────────────────────────────────────────────────────────

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

        // LIKE is case-sensitive in PostgreSQL
        $likeResults = $repo->findBy([Condition::like(EmployeeSchema::Name, 'uppercase')]);
        self::assertCount(0, $likeResults);

        $likeExact = $repo->findBy([Condition::like(EmployeeSchema::Name, 'UPPERCASE')]);
        self::assertCount(1, $likeExact);
        self::assertSame('UPPERCASE', $likeExact[0]->name);

        // ILIKE is case-insensitive
        $ilikeResults = $repo->findBy([Condition::ilike(EmployeeSchema::Name, 'uppercase')]);
        self::assertCount(1, $ilikeResults);

        $ilikeResults = $repo->findBy([Condition::ilike(EmployeeSchema::Name, 'mixedcase')]);
        self::assertCount(1, $ilikeResults);
    }

    // ── Combined conditions ──────────────────────────────────────────

    public function testMultipleConditionsCombinedWithAnd(): void
    {
        $repo = $this->getRepository();
        $repo->insertAll([
            new Employee(Uuid::uuid7()->toString(), 'Active Alice', new DateTimeImmutable(), null),
            (new Employee(Uuid::uuid7()->toString(), 'Deleted Alice', new DateTimeImmutable(), null))->withDeletedAt(),
            new Employee(Uuid::uuid7()->toString(), 'Active Bob', new DateTimeImmutable(), null),
        ]);

        $results = $repo->findBy([
            Condition::ilike(EmployeeSchema::Name, 'alice'),
            Condition::isNull(EmployeeSchema::DeletedAt),
        ]);

        self::assertCount(1, $results);
        self::assertSame('Active Alice', $results[0]->name);
    }

    public function testNotEqualsCondition(): void
    {
        $repo = $this->getRepository();
        $repo->insertAll([
            new Employee(Uuid::uuid7()->toString(), 'Alice', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'Bob', new DateTimeImmutable(), null),
            new Employee(Uuid::uuid7()->toString(), 'Charlie', new DateTimeImmutable(), null),
        ]);

        $results = $repo->findBy([Condition::notEquals(EmployeeSchema::Name, 'Bob')]);
        self::assertCount(2, $results);
    }
}
