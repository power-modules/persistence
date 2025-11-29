<?php

declare(strict_types=1);

namespace Modular\Persistence\Repository;

enum JoinType: string
{
    case Inner = 'INNER';
    case Left = 'LEFT';
    case Outer = 'OUTER';
}
