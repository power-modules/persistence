<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Repository\Fixture;

use DateTimeImmutable;
use Modular\Persistence\Schema\Contract\IHydrator;

/**
 * @implements IHydrator<Employee>
 */
class Hydrator implements IHydrator
{
    public function hydrate(array $data): mixed
    {
        return new Employee(
            $data[Schema::Id->value],
            $data[Schema::Name->value],
            $data[Schema::CreatedAt->value] ? new DateTimeImmutable($data[Schema::CreatedAt->value]) : new DateTimeImmutable(),
            $data[Schema::DeletedAt->value] ? new DateTimeImmutable($data[Schema::DeletedAt->value]) : null,
        );
    }

    public function dehydrate(mixed $entity): array
    {
        return [
            Schema::Id->value => $entity->id,
            Schema::Name->value => $entity->name,
            Schema::CreatedAt->value => $entity->createdAt->format('Y-m-d H:i:s'),
            Schema::DeletedAt->value => $entity->deletedAt?->format('Y-m-d H:i:s') ?? null,
        ];
    }

    public function getId(mixed $entity): int|string
    {
        return $entity->id;
    }

    public function getIdFieldName(): string
    {
        return Schema::Id->value;
    }
}
