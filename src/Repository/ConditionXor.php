<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository;

enum ConditionXor: string
{
    case And = 'AND';
    case Or = 'OR';
}
