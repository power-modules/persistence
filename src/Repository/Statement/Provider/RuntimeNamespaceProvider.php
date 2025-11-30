<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository\Statement\Provider;

use Modular\Persistence\Repository\Statement\Contract\INamespaceProvider;

class RuntimeNamespaceProvider implements INamespaceProvider
{
    private string $namespace = '';

    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }
}
