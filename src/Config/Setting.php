<?php

declare(strict_types=1);

namespace Modular\Persistence\Config;

enum Setting
{
    case Dsn;
    case Username;
    case Password;
    case Options;
}
