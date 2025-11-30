<?php

declare(strict_types=1);

namespace Modular\Persistence\Schema\Contract;

/**
 * @template TModel
 */
interface IHydrator
{
    /**
     * @param array<string,mixed> $data
     * @return TModel
     */
    public function hydrate(array $data): mixed;

    /**
     * @param TModel $entity
     * @return array<string,mixed>
     */
    public function dehydrate(mixed $entity): array;

    /**
     * @param TModel $entity
     */
    public function getId(mixed $entity): int|string;

    public function getIdFieldName(): string;
}
