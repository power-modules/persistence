<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement\Contract;

use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Join;

interface ISelectStatement extends IStatementHasParams
{
    public function getQuery(): string;
    public function one(): string;
    public function all(?int $start = null, ?int $limit = null): string;
    public function count(): string;
    public function addJoin(Join ...$joins): static;
    public function unshiftJoin(Join ...$joins): static;
    public function addCondition(Condition ...$conditions): static;
    public function addColumns(string ...$columns): self;
    public function addOrder(string $field, string $dir): static;
    public function addGroupBy(string $field): static;
    public function setStart(int $start): static;
    public function setLimit(?int $limit): static;
}
