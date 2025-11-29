<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement;

interface IStatementHasParams
{
    /**
     * @return array<Bind>
     */
    public function getWhereBinds(): array;
}
