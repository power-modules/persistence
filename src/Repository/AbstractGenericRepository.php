<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository;

use Modular\Persistence\Database\IDatabase;
use Modular\Persistence\Repository\Contract\IRepository;
use Modular\Persistence\Repository\Exception\EntityNotFoundException;
use Modular\Persistence\Repository\Exception\PreparedStatementException;
use Modular\Persistence\Repository\Exception\StatementExecutionException;
use Modular\Persistence\Repository\Statement\Contract\Bind;
use Modular\Persistence\Repository\Statement\Contract\IDeleteStatement;
use Modular\Persistence\Repository\Statement\Contract\IInsertStatement;
use Modular\Persistence\Repository\Statement\Contract\ISelectStatement;
use Modular\Persistence\Repository\Statement\Contract\IStatementFactory;
use Modular\Persistence\Repository\Statement\Contract\IUpdateStatement;
use Modular\Persistence\Repository\Statement\Factory\GenericStatementFactory;
use Modular\Persistence\Schema\Contract\IHydrator;
use PDOException;
use PDOStatement;

/**
 * @template TModel of object
 * @implements IRepository<TModel>
 */
abstract class AbstractGenericRepository implements IRepository
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

        $this->bindValues($statement, $selectStatement->getWhereBinds());

        try {
            if ($statement->execute() === false) {
                throw new PreparedStatementException();
            }
        } catch (PDOException $e) {
            throw new StatementExecutionException($e->getMessage(), (int) $e->getCode(), $e);
        }

        return $statement->fetch()['total_rows'] ?? 0;
    }

    /**
     * @param array<Condition> $conditions
     * @return array<int,TModel>
     * @throws PDOException
     * @throws PreparedStatementException
     */
    public function findBy(array $conditions = [], ?ISelectStatement $selectStatement = null, ?int $limit = null, int $offset = 0): array
    {
        $selectStatement = $selectStatement ? clone $selectStatement : $this->getSelectStatement();
        $selectStatement->addCondition(...$conditions);
        $selectStatement->all($offset > 0 ? $offset : null, $limit);

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
     * @return TModel
     * @throws EntityNotFoundException
     */
    public function findOrFail(int|string $id): mixed
    {
        return $this->find($id) ?? throw new EntityNotFoundException($this->getTableName(), $id);
    }

    /**
     * @param array<Condition> $conditions
     * @return TModel
     * @throws EntityNotFoundException
     */
    public function findOneByOrFail(array $conditions = [], ?ISelectStatement $selectStatement = null): mixed
    {
        return $this->findOneBy($conditions, $selectStatement) ?? throw new EntityNotFoundException($this->getTableName());
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

        $this->bindValues($statement, $updateStatement->getUpdateBinds());
        $this->bindValues($statement, $updateStatement->getWhereBinds());

        try {
            if ($statement->execute() === false) {
                throw new PreparedStatementException();
            }
        } catch (PDOException $e) {
            throw new StatementExecutionException($e->getMessage(), (int) $e->getCode(), $e);
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
     * @param int<1, max> $chunkSize
     */
    public function insertAll(array $entities, int $chunkSize = 100): int
    {
        $insertInTransaction = $this->database->inTransaction() === false;

        /**
         * @var array<array<TModel>>
         */
        $chunks = array_chunk($entities, $chunkSize);

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

            $this->bindValues($statement, $insertStatement->getInsertBinds());

            try {
                if ($statement->execute() === false) {
                    throw new PreparedStatementException();
                }
            } catch (PDOException $e) {
                throw new StatementExecutionException($e->getMessage(), (int) $e->getCode(), $e);
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

        $this->bindValues($statement, $deleteStatement->getWhereBinds());

        try {
            if ($statement->execute() === false) {
                throw new PreparedStatementException();
            }
        } catch (PDOException $e) {
            throw new StatementExecutionException($e->getMessage(), (int) $e->getCode(), $e);
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

        $this->bindValues($statement, $selectStatement->getWhereBinds());

        try {
            if ($statement->execute() === false) {
                throw new PreparedStatementException();
            }
        } catch (PDOException $e) {
            throw new StatementExecutionException($e->getMessage(), (int) $e->getCode(), $e);
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
     *
     * @deprecated Use upsert() instead — it performs the same insert-or-update in a single query.
     */
    #[\Deprecated(message: 'Use upsert() instead', since: '2.0')]
    public function save(object $entity): int
    {
        $id = $this->hydrator->getId($entity);
        $idFieldName = $this->hydrator->getIdFieldName();

        if ($this->exists([new Condition($idFieldName, Operator::Equals, $id)])) {
            return $this->update($entity);
        }

        return $this->insert($entity);
    }

    /**
     * Upsert: insert or update in a single query using ON CONFLICT ... DO UPDATE.
     *
     * @param TModel $entity
     */
    public function upsert(object $entity): int
    {
        $data = $this->hydrator->dehydrate($entity);
        $idFieldName = $this->hydrator->getIdFieldName();
        $columns = array_keys($data);
        $updateColumns = array_filter($columns, static fn (string $col): bool => $col !== $idFieldName);

        $insertStatement = $this->getInsertStatement($columns);
        $insertStatement->prepareBinds($data);
        $insertStatement->onConflictUpdate([$idFieldName], array_values($updateColumns));

        $statement = $this->database->prepare($insertStatement->getQuery());
        $this->bindValues($statement, $insertStatement->getInsertBinds());

        try {
            if ($statement->execute() === false) {
                throw new PreparedStatementException();
            }
        } catch (PDOException $e) {
            throw new StatementExecutionException($e->getMessage(), (int) $e->getCode(), $e);
        }

        return $statement->rowCount();
    }

    /**
     * @param array<array<string, mixed>> $rows
     * @return array<int, TModel>
     */
    protected function hydrateMany(array $rows): array
    {
        return array_map(fn ($row) => $this->hydrator->hydrate($row), $rows);
    }

    /**
     * @param array<Bind> $binds
     */
    protected function bindValues(PDOStatement $statement, array $binds): void
    {
        foreach ($binds as $bind) {
            $statement->bindValue($bind->name, $bind->value, $bind->type);
        }
    }

    abstract protected function getTableName(): string;
}
