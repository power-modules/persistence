<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Unit\Fixture;

use DateTimeImmutable;
use Modular\Persistence\Schema\Contract\IHydrator;

/**
 * @implements IHydrator<Employee>
 */
class EmployeeHydrator implements IHydrator
{
    public function hydrate(array $data): Employee
    {
        return new Employee(
            $data[EmployeeSchema::Id->value],
            $data[EmployeeSchema::Name->value],
            $data[EmployeeSchema::CreatedAt->value] ? new DateTimeImmutable($data[EmployeeSchema::CreatedAt->value]) : new DateTimeImmutable(),
            $data[EmployeeSchema::DeletedAt->value] ? new DateTimeImmutable($data[EmployeeSchema::DeletedAt->value]) : null,
        );
    }

    public function dehydrate(mixed $entity): array
    {
        return [
            EmployeeSchema::Id->value => $entity->id,
            EmployeeSchema::Name->value => $entity->name,
            EmployeeSchema::CreatedAt->value => $entity->createdAt->format('Y-m-d H:i:s'),
            EmployeeSchema::DeletedAt->value => $entity->deletedAt?->format('Y-m-d H:i:s') ?? null,
        ];
    }

    public function getId(mixed $entity): int|string
    {
        return $entity->id;
    }

    public function getIdFieldName(): string
    {
        return EmployeeSchema::Id->value;
    }
}
