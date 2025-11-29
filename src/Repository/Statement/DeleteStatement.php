<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement;

class DeleteStatement implements IDeleteStatement
{
    use TStatementHasParams;

    public function __construct(
        protected string $tableName,
        protected string $namespace = '',
    ) {
    }

    public function getQuery(): string
    {
        if ($this->namespace === '') {
            $this->statement = sprintf('DELETE FROM "%s"', $this->tableName);
        } else {
            $this->statement = sprintf('DELETE FROM "%s"."%s"', $this->namespace, $this->tableName);
        }

        $this->setupWhere();

        return $this->statement;
    }
}
