<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement;

use Modular\Persistence\Repository\Statement\Contract\Bind;
use Modular\Persistence\Repository\Statement\Contract\IUpdateStatement;

class UpdateStatement implements IUpdateStatement
{
    use TStatementHasParams;

    /**
     * @var array<Bind>
     */
    protected array $updateBinds = [];

    private int $idx = 0;

    public function __construct(
        protected string $tableName,
        protected string $namespace = '',
    ) {
    }

    public function getQuery(): string
    {
        $update = [];

        foreach ($this->updateBinds as $bind) {
            $update[] = sprintf('%s = %s', $bind->column, $bind->name);
        }

        if ($this->namespace === '') {
            $this->statement = sprintf('UPDATE "%s" SET %s', $this->tableName, implode(', ', $update));
        } else {
            $this->statement = sprintf('UPDATE "%s"."%s" SET %s', $this->namespace, $this->tableName, implode(', ', $update));
        }

        $this->setupWhere();

        return $this->statement;
    }

    public function prepareBinds(array $data): static
    {
        foreach ($data as $column => $value) {
            $this->updateBinds[] = $this->makeBind($column, $value, 'u_'. $this->idx++);
        }

        return $this;
    }

    public function getUpdateBinds(): array
    {
        return $this->updateBinds;
    }
}
