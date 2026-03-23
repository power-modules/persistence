<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Integration\Fixture;

use Modular\Persistence\Schema\Contract\IHydrator;
use Modular\Persistence\Schema\TStandardIdentity;

/**
 * @implements IHydrator<Note>
 */
class NoteHydrator implements IHydrator
{
    use TStandardIdentity;

    public function hydrate(array $data): Note
    {
        return new Note((string) $data['id'], (string) $data['title']);
    }

    public function dehydrate(mixed $entity): array
    {
        return ['id' => $entity->id, 'title' => $entity->title];
    }
}
