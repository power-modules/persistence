<?php

declare(strict_types=1);

namespace Modular\Persistence\Schema;

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
}
