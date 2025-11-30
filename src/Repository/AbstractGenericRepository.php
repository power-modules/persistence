<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository;

use Modular\Persistence\Database\IDatabase;
use Modular\Persistence\Repository\Exception\PreparedStatementException;
use Modular\Persistence\Repository\Statement\Contract\IDeleteStatement;
use Modular\Persistence\Repository\Statement\Contract\IInsertStatement;
use Modular\Persistence\Repository\Statement\Contract\ISelectStatement;
use Modular\Persistence\Repository\Statement\Contract\IStatementFactory;
use Modular\Persistence\Repository\Statement\Contract\IUpdateStatement;
use Modular\Persistence\Repository\Statement\Factory\GenericStatementFactory;
use Modular\Persistence\Schema\Contract\IHydrator;
use PDOException;
use Throwable;

/**
 * @template T
 */
abstract class AbstractGenericRepository
{
    /**
     * @param IHydrator<T> $hydrator
     */
    public function __construct(
        protected readonly IDatabase $database,
        public readonly IHydrator $hydrator,
        protected readonly IStatementFactory $statementFactory = new GenericStatementFactory(),
    ) {
    }

    public function has(Condition ...$conditions): bool
    {
        return $this->getTotal(null, ...$conditions) > 0;
    }

    public function getTotal(?ISelectStatement $selectStatement = null, Condition ...$conditions): int
    {
        $selectStatement ??= $this->getSelectStatement();
        $selectStatement->addCondition(...$conditions);
        $statement = $this->database->prepare($selectStatement->count());

        foreach ($selectStatement->getWhereBinds() as $whereBind) {
            if ($whereBind->value === null) {
                continue;
            }

            $statement->bindValue($whereBind->name, $whereBind->value, $whereBind->type);
        }

        if ($statement->execute() === false) {
            throw new PreparedStatementException();
        }

        return $statement->fetch()['total_rows'] ?? 0;
    }

    /**
     * @return array<int,T>
     * @throws PDOException
     * @throws PreparedStatementException
     */
    public function getMany(Condition ...$condition): array
    {
        $selectStatement = $this
            ->getSelectStatement()
            ->addCondition(...$condition)
        ;

        $selectStatement->all();

        $rows = $this->select($selectStatement);

        return array_map(fn ($row) => $this->hydrator->hydrate($row), $rows);
    }

    /**
     * @return null|T
     * @throws PDOException
     * @throws PreparedStatementException
     */
    public function findOne(Condition ...$condition): mixed
    {
        $selectStatement = $this
            ->getSelectStatement()
            ->addCondition(...$condition)
        ;

        $selectStatement->one();

        $rows = $this->select($selectStatement);

        if (count($rows) === 0) {
            return null;
        }

        return $this->hydrator->hydrate($rows[0]);
    }

    /**
     * @return null|T
     */
    public function getOne(int|string $id): mixed
    {
        return $this->getMany(
            new Condition($this->hydrator->getIdFieldName(), Operator::Equals, $id),
        )[0] ?? null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateMany(array $data, Condition ...$condition): int
    {
        $updateStatement = $this
            ->getUpdateStatement()
            ->prepareBinds($data)
            ->addCondition(...$condition)
        ;
        $statement = $this->database->prepare($updateStatement->getQuery());

        foreach ($updateStatement->getUpdateBinds() as $updateBind) {
            $statement->bindValue($updateBind->name, $updateBind->value, $updateBind->type);
        }

        foreach ($updateStatement->getWhereBinds() as $whereBind) {
            if ($whereBind->value === null) {
                continue;
            }

            $statement->bindValue($whereBind->name, $whereBind->value, $whereBind->type);
        }

        if ($statement->execute() === false) {
            throw new PreparedStatementException();
        }

        return $statement->rowCount();
    }

    /**
     * @param T $entity
     */
    public function updateOne($entity): int
    {
        $data = $this->hydrator->dehydrate($entity);
        $id = $this->hydrator->getId($entity);
        $idFieldName = $this->hydrator->getIdFieldName();

        unset($data[$idFieldName]);

        return $this->updateMany(
            $data,
            new Condition($idFieldName, Operator::Equals, $id),
        );
    }

    /**
     * @param array<T> $entities
     */
    public function insertMany(array $entities): int
    {
        $insertInTransaction = $this->database->inTransaction() === false;

        /**
         * @var array<array<T>>
         */
        $chunks = array_chunk($entities, 100);

        if ($insertInTransaction === true) {
            $this->database->beginTransaction();
        }

        $rowsInserted = 0;

        foreach ($chunks as $chunkEntities) {
            $columns = array_keys($this->hydrator->dehydrate($chunkEntities[0]));
            $insertStatement = $this->getInsertStatement($columns);

            foreach ($chunkEntities as $entity) {
                $insertStatement->prepareBinds($this->hydrator->dehydrate($entity));
            }

            $statement = $this->database->prepare($insertStatement->getQuery());

            foreach ($insertStatement->getInsertBinds() as $bind) {
                $statement->bindValue($bind->name, $bind->value, $bind->type);
            }

            if ($statement->execute() === false) {
                throw new PreparedStatementException();
            }

            $rowsInserted += $statement->rowCount();
        }

        if ($insertInTransaction === true) {
            $this->database->commit();
        }

        return $rowsInserted;
    }

    /**
     * @param T $entity
     */
    public function insertOne($entity): int
    {
        return $this->insertMany([$entity]);
    }

    public function deleteMany(Condition ...$condition): int
    {
        $deleteStatement = $this
            ->getDeleteStatement()
            ->addCondition(...$condition)
        ;
        $statement = $this->database->prepare($deleteStatement->getQuery());

        foreach ($deleteStatement->getWhereBinds() as $whereBind) {
            if ($whereBind->value === null) {
                continue;
            }

            $statement->bindValue($whereBind->name, $whereBind->value, $whereBind->type);
        }

        if ($statement->execute() === false) {
            throw new PreparedStatementException();
        }

        return $statement->rowCount();
    }

    public function deleteOne(int|string $id): int
    {
        return $this->deleteMany(
            new Condition($this->hydrator->getIdFieldName(), Operator::Equals, $id),
        );
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function select(ISelectStatement $selectStatement): array
    {
        $statement = $this->database->prepare($selectStatement->getQuery());

        foreach ($selectStatement->getWhereBinds() as $whereBind) {
            if ($whereBind->value === null) {
                continue;
            }

            $statement->bindValue($whereBind->name, $whereBind->value, $whereBind->type);
        }

        try {
            if ($statement->execute() === false) {
                throw new PreparedStatementException();
            }
        } catch (Throwable $e) {
            file_put_contents('/tmp/sql_error.log', $e->getMessage() . "\n" . $selectStatement->getQuery() . "\n", FILE_APPEND);

            throw $e;
        }

        return $statement->fetchAll();
    }

    protected function getSelectStatement(): ISelectStatement
    {
        return $this->statementFactory->createSelectStatement($this->getTableName());
    }

    protected function getUpdateStatement(): IUpdateStatement
    {
        return $this->statementFactory->createUpdateStatement($this->getTableName());
    }

    /**
     * @param array<string> $columns
     */
    protected function getInsertStatement(array $columns): IInsertStatement
    {
        return $this->statementFactory->createInsertStatement($this->getTableName(), $columns);
    }

    protected function getDeleteStatement(): IDeleteStatement
    {
        return $this->statementFactory->createDeleteStatement($this->getTableName());
    }

    /**
     * @param T $entity
     */
    public function save($entity): int
    {
        $id = $this->hydrator->getId($entity);
        $idFieldName = $this->hydrator->getIdFieldName();

        if ($this->has(new Condition($idFieldName, Operator::Equals, $id))) {
            return $this->updateOne($entity);
        }

        return $this->insertOne($entity);
    }

    abstract protected function getTableName(): string;
}
