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
 * @template TModel of object
 */
abstract class AbstractGenericRepository
{
    /**
     * @param IHydrator<TModel> $hydrator
     */
    public function __construct(
        protected readonly IDatabase $database,
        protected readonly IHydrator $hydrator,
        protected readonly IStatementFactory $statementFactory = new GenericStatementFactory(),
    ) {
    }

    /**
     * @param array<Condition> $conditions
     */
    public function exists(array $conditions = []): bool
    {
        return $this->count($conditions) > 0;
    }

    /**
     * @param array<Condition> $conditions
     */
    public function count(array $conditions = [], ?ISelectStatement $selectStatement = null): int
    {
        $selectStatement = $selectStatement ? clone $selectStatement : $this->getSelectStatement();
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
     * @param array<Condition> $conditions
     * @return array<int,TModel>
     * @throws PDOException
     * @throws PreparedStatementException
     */
    public function findBy(array $conditions = [], ?ISelectStatement $selectStatement = null): array
    {
        $selectStatement = $selectStatement ? clone $selectStatement : $this->getSelectStatement();
        $selectStatement->addCondition(...$conditions);
        $selectStatement->all();

        $rows = $this->select($selectStatement);

        return $this->hydrateMany($rows);
    }

    /**
     * @param array<Condition> $conditions
     * @return null|TModel
     * @throws PDOException
     * @throws PreparedStatementException
     */
    public function findOneBy(array $conditions = [], ?ISelectStatement $selectStatement = null): mixed
    {
        $selectStatement = $selectStatement ? clone $selectStatement : $this->getSelectStatement();
        $selectStatement->addCondition(...$conditions);

        $selectStatement->one();

        $rows = $this->select($selectStatement);

        if (count($rows) === 0) {
            return null;
        }

        return $this->hydrator->hydrate($rows[0]);
    }

    /**
     * @return null|TModel
     */
    public function find(int|string $id): mixed
    {
        return $this->findBy(
            [new Condition($this->hydrator->getIdFieldName(), Operator::Equals, $id)],
        )[0] ?? null;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<Condition> $conditions
     */
    public function updateBy(array $data, array $conditions = []): int
    {
        $updateStatement = $this
            ->getUpdateStatement()
            ->prepareBinds($data)
            ->addCondition(...$conditions)
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
     * @param TModel $entity
     */
    public function update(object $entity): int
    {
        $data = $this->hydrator->dehydrate($entity);
        $id = $this->hydrator->getId($entity);
        $idFieldName = $this->hydrator->getIdFieldName();

        unset($data[$idFieldName]);

        return $this->updateBy(
            $data,
            [new Condition($idFieldName, Operator::Equals, $id)],
        );
    }

    /**
     * @param array<TModel> $entities
     */
    public function insertAll(array $entities): int
    {
        $insertInTransaction = $this->database->inTransaction() === false;

        /**
         * @var array<array<TModel>>
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
     * @param TModel $entity
     */
    public function insert(object $entity): int
    {
        return $this->insertAll([$entity]);
    }

    /**
     * @param array<Condition> $conditions
     */
    public function deleteBy(array $conditions = []): int
    {
        $deleteStatement = $this
            ->getDeleteStatement()
            ->addCondition(...$conditions)
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

    public function delete(int|string $id): int
    {
        return $this->deleteBy(
            [new Condition($this->hydrator->getIdFieldName(), Operator::Equals, $id)],
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
     * @param TModel $entity
     */
    public function save($entity): int
    {
        $id = $this->hydrator->getId($entity);
        $idFieldName = $this->hydrator->getIdFieldName();

        if ($this->exists([new Condition($idFieldName, Operator::Equals, $id)])) {
            return $this->update($entity);
        }

        return $this->insert($entity);
    }

    /**
     * @param array<array<string, mixed>> $rows
     * @return array<int, TModel>
     */
    protected function hydrateMany(array $rows): array
    {
        return array_map(fn ($row) => $this->hydrator->hydrate($row), $rows);
    }

    abstract protected function getTableName(): string;
}
