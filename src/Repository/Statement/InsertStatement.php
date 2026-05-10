<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement;

use Modular\Persistence\Repository\Statement\Contract\Bind;
use Modular\Persistence\Repository\Statement\Contract\IInsertStatement;
use Modular\Persistence\Repository\Statement\Contract\ISqlDialect;
use Modular\Persistence\Repository\Statement\Dialect\PostgresDialect;

class InsertStatement implements IInsertStatement
{
    private ISqlDialect $sqlDialect;

    /**
     * @var array<Bind>
     */
    private array $insertBinds = [];

    /**
     * @var array<string>
     */
    private array $placeholders = [];

    private int $idx = 0;
    private string $tableName;
    private string $namespace;
    private bool $ignoreDuplicates = false;

    /**
     * @var array<string>
     */
    private array $columns;

    /**
     * @var array<string>
     */
    private array $conflictColumns = [];

    /**
     * @var array<string>
     */
    private array $updateColumns = [];

    /**
     * @param array<string> $columns
     */
    public function __construct(
        string $tableName,
        array $columns,
        string $namespace = '',
        ?ISqlDialect $sqlDialect = null,
    ) {
        $this->tableName = $tableName;
        $this->namespace = $namespace;
        $this->columns = $columns;
        $this->sqlDialect = $sqlDialect ?? new PostgresDialect();
    }

    public function getQuery(): string
    {
        return sprintf(
            '%s %s%s',
            $this->getInsertPrefix(),
            implode(', ', $this->placeholders),
            $this->sqlDialect->buildInsertConflictClause(
                $this->ignoreDuplicates,
                $this->conflictColumns,
                $this->updateColumns,
            ),
        );
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
        $this->ignoreDuplicates = true;
        $this->conflictColumns = [];
        $this->updateColumns = [];

        return $this;
    }

    /**
     * @param array<string> $conflictColumns
     * @param array<string> $updateColumns
     */
    public function onConflictUpdate(array $conflictColumns, array $updateColumns): static
    {
        $this->ignoreDuplicates = false;
        $this->conflictColumns = $conflictColumns;
        $this->updateColumns = $updateColumns;

        return $this;
    }

    private function getInsertPrefix(): string
    {
        $columns = implode(', ', array_map($this->sqlDialect->quoteIdentifier(...), $this->columns));

        return sprintf(
            '%s %s (%s) VALUES',
            $this->sqlDialect->getInsertCommand($this->ignoreDuplicates),
            $this->sqlDialect->qualifyIdentifier($this->namespace, $this->tableName),
            $columns,
        );
    }
}
