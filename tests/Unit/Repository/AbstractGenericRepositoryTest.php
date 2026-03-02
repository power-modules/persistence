<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Unit\Repository;

use Modular\Persistence\Database\IDatabase;
use Modular\Persistence\Repository\AbstractGenericRepository;
use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Join;
use Modular\Persistence\Repository\JoinType;
use Modular\Persistence\Repository\Statement\Contract\Bind;
use Modular\Persistence\Repository\Statement\Factory\GenericStatementFactory;
use Modular\Persistence\Schema\Contract\IHydrator;
use Modular\Persistence\Tests\Unit\Fixture\Employee;
use Modular\Persistence\Tests\Unit\Fixture\EmployeeHydrator;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Merged from RepositorySqlGenerationTest + the null-binding regression from
 * AbstractGenericRepositoryInteractionTest.  All SQL generation and bind
 * behaviour for the repository layer lives here.
 */
#[CoversClass(AbstractGenericRepository::class)]
final class AbstractGenericRepositoryTest extends TestCase
{
    // ── SQL Generation ──

    public function testFindWithAliasAndJoin(): void
    {
        $pdoStatement = $this->createStub(PDOStatement::class);
        $pdoStatement->method('execute')->willReturn(true);
        $pdoStatement->method('fetchAll')->willReturn([]);

        $database = $this->createMock(IDatabase::class);
        $database->expects(self::once())
            ->method('prepare')
            ->with(self::callback(function (string $sql): bool {
                $sql = (string) preg_replace('/\s+/', ' ', $sql);

                return str_contains($sql, 'SELECT * FROM "employees" INNER JOIN "departments" "d" ON "d"."id" = "employees"."dept_id"')
                    && str_contains($sql, 'WHERE (d.name = :w_0_d_name)');
            }))
            ->willReturn($pdoStatement);

        $repository = new /** @extends AbstractGenericRepository<Employee> */ class ($database, new EmployeeHydrator()) extends AbstractGenericRepository {
            protected function getTableName(): string
            {
                return 'employees';
            }

            /** @return array<int, array<string, mixed>> */
            public function findByDepartment(string $deptName): array
            {
                $stmt = $this->getSelectStatement();
                $stmt->addJoin(new Join(JoinType::Inner, 'departments', 'dept_id', 'id', null, 'd'));
                $stmt->addCondition(Condition::equals('d.name', $deptName));
                $stmt->all();

                return $this->select($stmt);
            }
        };

        $repository->findByDepartment('Engineering');
    }

    public function testFindByWithRawConditionProducesCorrectSql(): void
    {
        $pdoStatement = $this->createStub(PDOStatement::class);
        $pdoStatement->method('execute')->willReturn(true);
        $pdoStatement->method('fetchAll')->willReturn([]);

        $database = $this->createMock(IDatabase::class);
        $database->expects(self::once())
            ->method('prepare')
            ->with(self::callback(function (string $sql): bool {
                $sql = (string) preg_replace('/\s+/', ' ', $sql);

                return str_contains($sql, 'WHERE (status = :w_0_status) AND ("metadata" @> :kw::jsonb)')
                    && str_contains($sql, 'FROM "employees"');
            }))
            ->willReturn($pdoStatement);

        $repository = new /** @extends AbstractGenericRepository<Employee> */ class ($database, new EmployeeHydrator()) extends AbstractGenericRepository {
            protected function getTableName(): string
            {
                return 'employees';
            }

            /** @return array<int, array<string, mixed>> */
            public function findByMetadata(string $status, string $jsonFilter): array
            {
                $stmt = $this->getSelectStatement();
                $stmt->addCondition(Condition::equals('status', $status));
                $stmt->addRawCondition('"metadata" @> :kw::jsonb', [
                    Bind::json('metadata', ':kw', $jsonFilter),
                ]);
                $stmt->all();

                return $this->select($stmt);
            }
        };

        $repository->findByMetadata('published', '{"lang":"en"}');
    }

    public function testCountWithRawConditionProducesCorrectSql(): void
    {
        $pdoStatement = $this->createStub(PDOStatement::class);
        $pdoStatement->method('execute')->willReturn(true);
        $pdoStatement->method('fetch')->willReturn(['total_rows' => 5]);

        $database = $this->createMock(IDatabase::class);
        $database->expects(self::once())
            ->method('prepare')
            ->with(self::callback(function (string $sql): bool {
                $sql = (string) preg_replace('/\s+/', ' ', $sql);

                return str_contains($sql, 'SELECT COUNT(*) as total_rows FROM "employees"')
                    && str_contains($sql, 'WHERE ("metadata" @> :kw::jsonb)');
            }))
            ->willReturn($pdoStatement);

        $repository = new /** @extends AbstractGenericRepository<Employee> */ class ($database, new EmployeeHydrator()) extends AbstractGenericRepository {
            protected function getTableName(): string
            {
                return 'employees';
            }

            public function countByMetadata(string $jsonFilter): int
            {
                $stmt = $this->getSelectStatement();
                $stmt->addRawCondition('"metadata" @> :kw::jsonb', [
                    Bind::json('metadata', ':kw', $jsonFilter),
                ]);

                return $this->count([], $stmt);
            }
        };

        $count = $repository->countByMetadata('{"status":"active"}');
        self::assertSame(5, $count);
    }

    // ── Null Binding Regression ──

    public function testNullValuesAreBound(): void
    {
        $database = $this->createMock(IDatabase::class);
        $statement = $this->createMock(PDOStatement::class);
        $hydrator = $this->createStub(IHydrator::class);
        $factory = new GenericStatementFactory();

        $repo = new /** @extends AbstractGenericRepository<\stdClass> */ class ($database, $hydrator, $factory) extends AbstractGenericRepository {
            protected function getTableName(): string
            {
                return 'test_table';
            }
        };

        $hydrator->method('dehydrate')->willReturn([
            'id' => 1,
            'name' => null,
        ]);
        $hydrator->method('getId')->willReturn(null);
        $hydrator->method('getIdFieldName')->willReturn('id');

        $database->expects(self::once())
            ->method('prepare')
            ->willReturn($statement);

        // If null-binding was broken, only the non-null column would get a bindValue call.
        $statement->expects(self::exactly(2))
            ->method('bindValue');

        $statement->method('execute')->willReturn(true);
        $statement->method('rowCount')->willReturn(1);

        $repo->insert(new \stdClass());
    }
}
