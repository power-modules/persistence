<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Integration\Fixture;

use DateTimeImmutable;
use Modular\Persistence\Schema\Contract\IHydrator;
use Modular\Persistence\Schema\TStandardIdentity;

/**
 * @implements IHydrator<Employee>
 */
class EmployeeHydrator implements IHydrator
{
    use TStandardIdentity;

    public function hydrate(array $data): Employee
    {
        return new Employee(
            $data[EmployeeSchema::Id->value],
            $data[EmployeeSchema::Name->value],
            new DateTimeImmutable($data[EmployeeSchema::CreatedAt->value]),
            $data[EmployeeSchema::DeletedAt->value] !== null ? new DateTimeImmutable($data[EmployeeSchema::DeletedAt->value]) : null,
        );
    }

    public function dehydrate(mixed $entity): array
    {
        return [
            EmployeeSchema::Id->value => $entity->id,
            EmployeeSchema::Name->value => $entity->name,
            EmployeeSchema::CreatedAt->value => $entity->createdAt->format('Y-m-d H:i:sP'),
            EmployeeSchema::DeletedAt->value => $entity->deletedAt?->format('Y-m-d H:i:sP'),
        ];
    }
}
