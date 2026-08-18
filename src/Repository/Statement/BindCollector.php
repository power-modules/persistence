<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement;

use Modular\Persistence\Repository\Statement\Contract\Bind;
use Modular\Persistence\Repository\Statement\Contract\IBindCollector;
use Modular\Persistence\Repository\Statement\Contract\ISelectStatement;

class BindCollector implements IBindCollector
{
    /**
     * @var array<Bind>
     */
    private array $binds = [];
    private int $index = 0;
    private int $subqueryIndex = 0;

    public function add(string $column, mixed $value): string
    {
        $cleanColumn = preg_replace('/[^a-zA-Z0-9_]/', '_', $column) ?? 'param';
        $name = sprintf(':w_%d_%s', $this->index++, $cleanColumn);

        $this->binds[] = Bind::create($column, $name, $value);

        return $name;
    }

    public function addJson(string $column, array|string $value): string
    {
        $cleanColumn = preg_replace('/[^a-zA-Z0-9_]/', '_', $column) ?? 'param';
        $name = sprintf(':w_%d_%s', $this->index++, $cleanColumn);

        $this->binds[] = Bind::json($column, $name, $value);

        return $name;
    }

    /**
     * @return array{sql: string, binds: array<Bind>}
     */
    public function embedSubquery(ISelectStatement $subquery): array
    {
        $sql = $subquery->getQuery();
        $binds = $subquery->getWhereBinds();
        $prefix = sprintf('sq%d_', $this->subqueryIndex++);

        // Rewrite longer placeholders first so shared prefixes (e.g. :w_1 vs :w_10) never partially match.
        usort($binds, static fn (Bind $a, Bind $b): int => strlen($b->name) <=> strlen($a->name));

        $renamed = [];
        foreach ($binds as $bind) {
            $newName = ':' . $prefix . ltrim($bind->name, ':');
            $sql = str_replace($bind->name, $newName, $sql);
            $renamedBind = new Bind($bind->column, $newName, $bind->value, $bind->type);
            $renamed[] = $renamedBind;
            $this->binds[] = $renamedBind;
        }

        return ['sql' => $sql, 'binds' => $renamed];
    }

    /**
     * @return array<Bind>
     */
    public function getBinds(): array
    {
        return $this->binds;
    }

    public function reset(): void
    {
        $this->binds = [];
        $this->index = 0;
        $this->subqueryIndex = 0;
    }
}
