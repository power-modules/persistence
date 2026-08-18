<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement;

use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\ConditionGroup;
use Modular\Persistence\Repository\Statement\Contract\Bind;
use Modular\Persistence\Repository\Statement\Contract\IConditionRenderer;

class WhereClause
{
    /**
     * @var array<int,Condition[]>
     */
    private array $conditions = [];

    /**
     * @var array<ConditionGroup>
     */
    private array $groups = [];

    /**
     * @var array<array{sql: string, binds: array<Bind>}>
     */
    private array $rawConditions = [];

    private readonly IConditionRenderer $renderer;

    public function __construct(?IConditionRenderer $renderer = null)
    {
        $this->renderer = $renderer ?? new ConditionRenderer();
    }

    public function add(Condition ...$conditions): self
    {
        if (count($conditions) === 0) {
            return $this;
        }

        $this->conditions[] = array_values($conditions);

        return $this;
    }

    /**
     * Add a parenthesized, possibly nested boolean group of conditions.
     *
     * The group is AND-joined with the rest of the WHERE clause. Use nested
     * groups to express boolean trees such as `(a AND b) OR (c)`.
     */
    public function addGroup(ConditionGroup $group): self
    {
        $this->groups[] = $group;

        return $this;
    }

    /**
     * Add a raw SQL condition with optional bind values.
     *
     * The SQL fragment is included as an AND-joined group in the WHERE clause.
     * Use this for expressions that cannot be represented via Condition (e.g. JSONB operators with casts).
     *
     * @param array<Bind> $binds
     */
    public function addRaw(string $sql, array $binds = []): self
    {
        $this->rawConditions[] = ['sql' => $sql, 'binds' => $binds];

        return $this;
    }

    public function toSql(): string
    {
        return $this->build()['sql'];
    }

    /**
     * @return array<Bind>
     */
    public function getBinds(): array
    {
        return $this->build()['binds'];
    }

    /**
     * Render the clause once, producing both the SQL string and its binds so the
     * two can never drift apart.
     *
     * @return array{sql: string, binds: array<Bind>}
     */
    private function build(): array
    {
        $bindCollector = new BindCollector();
        $clauses = [];

        foreach ($this->conditions as $group) {
            $parts = [];

            foreach ($group as $position => $condition) {
                $rendered = $this->renderer->render($condition, null, $bindCollector);

                if ($position > 0) {
                    $rendered = sprintf('%s %s', $condition->xor->value, $rendered);
                }

                $parts[] = $rendered;
            }

            $clauses[] = implode(' ', $parts);
        }

        foreach ($this->groups as $group) {
            $rendered = $this->renderer->render($group, null, $bindCollector);

            if ($rendered === '') {
                continue;
            }

            $clauses[] = $rendered;
        }

        $binds = $bindCollector->getBinds();

        foreach ($this->rawConditions as $raw) {
            $clauses[] = $raw['sql'];
            $binds = array_merge($binds, $raw['binds']);
        }

        if (count($clauses) === 0) {
            return ['sql' => '', 'binds' => []];
        }

        return [
            'sql' => sprintf(' WHERE (%s)', implode(') AND (', $clauses)),
            'binds' => $binds,
        ];
    }
}
