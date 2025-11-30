<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement\Contract;

interface IStatementHasParams
{
    /**
     * @return array<Bind>
     */
    public function getWhereBinds(): array;
}
