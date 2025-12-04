<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement;

use Modular\Persistence\Repository\Statement\Contract\Bind;
use Modular\Persistence\Repository\Statement\Contract\IInsertStatement;

class InsertStatement implements IInsertStatement
{
    private string $statement = '';

    /**
     * @var array<Bind>
     */
    private array $insertBinds = [];

    /**
     * @var array<string>
     */
    private array $placeholders = [];

    private int $idx = 0;
    private string $onConflict = '';

    /**
     * @param array<string> $columns
     */
    public function __construct(
        string $tableName,
        array $columns,
        string $namespace = '',
    ) {
        if ($namespace === '') {
            $this->statement = sprintf('INSERT INTO "%s" ("%s") VALUES', $tableName, implode('", "', $columns));
        } else {
            $this->statement = sprintf('INSERT INTO "%s"."%s" ("%s") VALUES', $namespace, $tableName, implode('", "', $columns));
        }
    }

    public function getQuery(): string
    {
        return sprintf('%s %s%s', $this->statement, implode(', ', $this->placeholders), $this->onConflict);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function prepareBinds(array $data): static
    {
        $values = [];

        foreach ($data as $column => $value) {
            $this->insertBinds[] = $bind = Bind::create($column, ':i_' . $this->idx++, $value);
            $values[] = $bind->name;
        }

        $this->placeholders[] = sprintf('(%s)', implode(',', $values));

        return $this;
    }

    public function getInsertBinds(): array
    {
        return $this->insertBinds;
    }

    public function ignoreDuplicates(): static
    {
        $this->onConflict = sprintf(' ON CONFLICT DO NOTHING');

        return $this;
    }

    /**
     * @param array<string> $conflictColumns
     * @param array<string> $updateColumns
     */
    public function onConflictUpdate(array $conflictColumns, array $updateColumns): static
    {
        $setClauses = array_map(
            fn (string $col) => sprintf('"%s" = EXCLUDED."%s"', $col, $col),
            $updateColumns,
        );

        $this->onConflict = sprintf(
            ' ON CONFLICT ("%s") DO UPDATE SET %s',
            implode('", "', $conflictColumns),
            implode(', ', $setClauses),
        );

        return $this;
    }
}
