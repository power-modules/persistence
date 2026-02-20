<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Repository;

use Modular\Persistence\Database\IDatabase;
use Modular\Persistence\Repository\AbstractGenericRepository;
use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Join;
use Modular\Persistence\Repository\JoinType;
use Modular\Persistence\Repository\Statement\Contract\Bind;
use Modular\Persistence\Test\Unit\Repository\Fixture\Employee;
use Modular\Persistence\Test\Unit\Repository\Fixture\Hydrator;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractGenericRepository::class)]
class RepositorySqlGenerationTest extends TestCase
{
    public function testFindWithAliasAndJoin(): void
    {
        $pdoStatement = $this->createStub(PDOStatement::class);
        $pdoStatement->method('execute')->willReturn(true);
        $pdoStatement->method('fetchAll')->willReturn([]);

        $database = $this->createMock(IDatabase::class);
        $database->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function (string $sql) {
                // Normalize whitespace for easier comparison
                $sql = (string) preg_replace('/\s+/', ' ', $sql);

                // Expected SQL:
                // SELECT * FROM "employees"
                // INNER JOIN "departments" "d" ON "d"."id" = "employees"."dept_id"
                // WHERE (d.name = :w_0_d_name) LIMIT 500 OFFSET 0

                $expectedPart1 = 'SELECT * FROM "employees" INNER JOIN "departments" "d" ON "d"."id" = "employees"."dept_id"';
                $expectedPart2 = 'WHERE (d.name = :w_0_d_name)';

                $containsPart1 = str_contains($sql, $expectedPart1);
                $containsPart2 = str_contains($sql, $expectedPart2);

                return $containsPart1 && $containsPart2;
            }))
            ->willReturn($pdoStatement);

        $repository = new /** @extends AbstractGenericRepository<Employee> */ class ($database, new Hydrator()) extends AbstractGenericRepository {
            protected function getTableName(): string
            {
                return 'employees';
            }

            /**
             * @return array<int, array<string, mixed>>
             */
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
        $database->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function (string $sql) {
                $sql = (string) preg_replace('/\s+/', ' ', $sql);

                // Should have both standard and raw conditions
                return str_contains($sql, 'WHERE (status = :w_0_status) AND ("metadata" @> :kw::jsonb)')
                    && str_contains($sql, 'FROM "employees"');
            }))
            ->willReturn($pdoStatement);

        $repository = new /** @extends AbstractGenericRepository<Employee> */ class ($database, new Hydrator()) extends AbstractGenericRepository {
            protected function getTableName(): string
            {
                return 'employees';
            }

            /**
             * @return array<int, array<string, mixed>>
             */
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
        $database->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function (string $sql) {
                $sql = (string) preg_replace('/\s+/', ' ', $sql);

                return str_contains($sql, 'SELECT COUNT(*) as total_rows FROM "employees"')
                    && str_contains($sql, 'WHERE ("metadata" @> :kw::jsonb)');
            }))
            ->willReturn($pdoStatement);

        $repository = new /** @extends AbstractGenericRepository<Employee> */ class ($database, new Hydrator()) extends AbstractGenericRepository {
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
}
