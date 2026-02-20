<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Contract;

use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Exception\EntityNotFoundException;
use Modular\Persistence\Repository\Statement\Contract\ISelectStatement;

/**
 * @template TModel of object
 */
interface IRepository
{
    /**
     * @param array<Condition> $conditions
     */
    public function exists(array $conditions = []): bool;

    /**
     * @param array<Condition> $conditions
     */
    public function count(array $conditions = [], ?ISelectStatement $selectStatement = null): int;

    /**
     * @param array<Condition> $conditions
     * @return array<int, TModel>
     */
    public function findBy(array $conditions = [], ?ISelectStatement $selectStatement = null, ?int $limit = null, int $offset = 0): array;

    /**
     * @param array<Condition> $conditions
     * @return null|TModel
     */
    public function findOneBy(array $conditions = [], ?ISelectStatement $selectStatement = null): mixed;

    /**
     * @return null|TModel
     */
    public function find(int|string $id): mixed;

    /**
     * @return TModel
     * @throws EntityNotFoundException
     */
    public function findOrFail(int|string $id): mixed;

    /**
     * @param array<Condition> $conditions
     * @return TModel
     * @throws EntityNotFoundException
     */
    public function findOneByOrFail(array $conditions = [], ?ISelectStatement $selectStatement = null): mixed;

    /**
     * @param array<string, mixed> $data
     * @param array<Condition> $conditions
     */
    public function updateBy(array $data, array $conditions = []): int;

    /**
     * @param TModel $entity
     */
    public function update(object $entity): int;

    /**
     * @param array<TModel> $entities
     * @param int<1, max> $chunkSize
     */
    public function insertAll(array $entities, int $chunkSize = 100): int;

    /**
     * @param TModel $entity
     */
    public function insert(object $entity): int;

    /**
     * @param array<Condition> $conditions
     */
    public function deleteBy(array $conditions = []): int;

    public function delete(int|string $id): int;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function select(ISelectStatement $selectStatement): array;

    /**
     * @param TModel $entity
     *
     * @deprecated Use upsert() instead — it performs the same insert-or-update in a single query.
     */
    public function save(object $entity): int;

    /**
     * @param TModel $entity
     */
    public function upsert(object $entity): int;
}
