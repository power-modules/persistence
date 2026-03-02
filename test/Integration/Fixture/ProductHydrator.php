<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Integration\Fixture;

use DateTimeImmutable;
use Modular\Persistence\Schema\Contract\IHydrator;
use Modular\Persistence\Schema\TStandardIdentity;

/**
 * @implements IHydrator<Product>
 */
class ProductHydrator implements IHydrator
{
    use TStandardIdentity;

    public function hydrate(array $data): Product
    {
        $metadata = $data[ProductSchema::Metadata->value];
        $tags = $data[ProductSchema::Tags->value];

        return new Product(
            $data[ProductSchema::Id->value],
            $data[ProductSchema::Name->value],
            $metadata !== null ? json_decode($metadata, true, 512, JSON_THROW_ON_ERROR) : null,
            $tags !== null ? json_decode($tags, true, 512, JSON_THROW_ON_ERROR) : null,
            new DateTimeImmutable($data[ProductSchema::CreatedAt->value]),
        );
    }

    public function dehydrate(mixed $entity): array
    {
        return [
            ProductSchema::Id->value => $entity->id,
            ProductSchema::Name->value => $entity->name,
            ProductSchema::Metadata->value => $entity->metadata !== null ? json_encode($entity->metadata, JSON_THROW_ON_ERROR) : null,
            ProductSchema::Tags->value => $entity->tags !== null ? json_encode($entity->tags, JSON_THROW_ON_ERROR) : null,
            ProductSchema::CreatedAt->value => $entity->createdAt->format('Y-m-d H:i:sP'),
        ];
    }
}
