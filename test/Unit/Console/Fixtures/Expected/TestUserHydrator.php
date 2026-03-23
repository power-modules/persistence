<?php

declare(strict_types=1);

namespace App\Hydrator;

use App\Domain\TestUser;
use Modular\Persistence\Schema\Contract\IHydrator;
use Modular\Persistence\Schema\TStandardIdentity;
use Modular\Persistence\Test\Unit\Console\Fixtures\TestUserSchema;

/**
 * @implements IHydrator<TestUser>
 */
class TestUserHydrator implements IHydrator
{
    use TStandardIdentity;

    /**
     * @param array<string, mixed> $data
     */
    public function hydrate(array $data): TestUser
    {
        return new TestUser(
            $data[TestUserSchema::Id->value],
            $data[TestUserSchema::Name->value],
            $data[TestUserSchema::Email->value],
            isset($data[TestUserSchema::Age->value]) ? (int)$data[TestUserSchema::Age->value] : null,
            isset($data[TestUserSchema::IsActive->value]) ? (int)$data[TestUserSchema::IsActive->value] : null,
            new \DateTimeImmutable($data[TestUserSchema::CreatedAt->value]),
        );
    }

    /**
     * @param TestUser $entity
     * @return array<string, mixed>
     */
    public function dehydrate(mixed $entity): array
    {
        return [
            TestUserSchema::Id->value => $entity->id,
            TestUserSchema::Name->value => $entity->name,
            TestUserSchema::Email->value => $entity->email,
            TestUserSchema::Age->value => $entity->age,
            TestUserSchema::IsActive->value => $entity->isActive,
            TestUserSchema::CreatedAt->value => $entity->createdAt->format('Y-m-d H:i:sP'),
        ];
    }
}
