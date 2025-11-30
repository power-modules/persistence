<?php

declare(strict_types=1);

namespace Modular\Persistence\Schema;

trait TStandardIdentity
{
    public function getId(mixed $entity): int|string|null
    {
        return $entity->id ?? null;
    }

    public function getIdFieldName(): string
    {
        return 'id';
    }
}
