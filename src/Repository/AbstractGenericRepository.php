<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository;

use Modular\Persistence\IDatabase;
use Modular\Persistence\Repository\Exception\PreparedStatementException;
use Modular\Persistence\Repository\Statement\DeleteStatement;
use Modular\Persistence\Repository\Statement\IDeleteStatement;
use Modular\Persistence\Repository\Statement\IInsertStatement;
use Modular\Persistence\Repository\Statement\InsertStatement;
use Modular\Persistence\Repository\Statement\ISelectStatement;
use Modular\Persistence\Repository\Statement\IUpdateStatement;
use Modular\Persistence\Repository\Statement\SelectStatement;
use Modular\Persistence\Repository\Statement\UpdateStatement;
use Modular\Persistence\Schema\IHydrator;
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
    ) {
    }

    public function beginTransaction(): bool
    {
        return $this->database->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->database->commit();
    }

    public function inTransaction(): bool
    {
        return $this->database->inTransaction();
    }

    public function rollback(): bool
    {
        return $this->database->rollBack();
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
        $statement = $this->database->prepare($selectStatement->all());

        foreach ($selectStatement->getWhereBinds() as $whereBind) {
            if ($whereBind->value === null) {
                continue;
            }

            $statement->bindValue($whereBind->name, $whereBind->value, $whereBind->type);
        }

        if ($statement->execute() === false) {
            throw new PreparedStatementException();
        }

        $models = [];

        do {
            $row = $statement->fetch();

            if ($row === false) {
                break;
            }

            $models[] = $this->hydrator->hydrate($row);
        } while (true);

        return $models;
    }

    /**
     * @return null|T
     */
    public function getOne(int|string $id): mixed
    {
        return $this->getMany(
            new Condition($this->getIdFieldName(), Operator::Equals, $id),
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
    public function updateOne($entity, int|string $id): int
    {
        $data = [];

        foreach ($this->hydrator->dehydrate($entity) as $column => $value) {
            if ($column === $this->getIdFieldName()) {
                continue;
            }

            $data[$column] = $value;
        }

        return $this->updateMany(
            $data,
            new Condition($this->getIdFieldName(), Operator::Equals, $id),
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
            new Condition($this->getIdFieldName(), Operator::Equals, $id),
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
        return new SelectStatement($this->getTableName());
    }

    protected function getUpdateStatement(): IUpdateStatement
    {
        return new UpdateStatement($this->getTableName());
    }

    /**
     * @param array<string> $columns
     */
    protected function getInsertStatement(array $columns): IInsertStatement
    {
        return new InsertStatement($this->getTableName(), $columns);
    }

    protected function getDeleteStatement(): IDeleteStatement
    {
        return new DeleteStatement($this->getTableName());
    }

    abstract protected function getIdFieldName(): string;

    abstract protected function getTableName(): string;
}
