<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Integration\Fixture;

use Modular\Persistence\Repository\AbstractGenericRepository;

/**
 * @extends AbstractGenericRepository<Note>
 */
class NoteRepository extends AbstractGenericRepository
{
    protected function getTableName(): string
    {
        return 'notes';
    }
}
