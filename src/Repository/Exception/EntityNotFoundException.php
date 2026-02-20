<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Exception;

use Modular\Persistence\Exception\PersistenceException;

class EntityNotFoundException extends PersistenceException
{
    public function __construct(string $tableName, int|string|null $id = null)
    {
        $message = $id !== null
            ? sprintf('Entity not found in "%s" with id "%s".', $tableName, $id)
            : sprintf('Entity not found in "%s".', $tableName);

        parent::__construct($message);
    }
}
