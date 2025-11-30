<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement\Contract;

interface INamespaceProvider
{
    public function getNamespace(): string;
}
