<?php

declare(strict_types=1);

namespace Modular\Persistence\Schema\Definition;

enum IndexType
{
    case Btree;
    case Hash;
    case Gin;
    case Gist;
    case SpGist;
    case Brin;

    public function getDbType(): string
    {
        return match ($this) {
            self::Btree => 'BTREE',
            self::Hash => 'HASH',
            self::Gin => 'GIN',
            self::Gist => 'GiST',
            self::SpGist => 'SP-GiST',
            self::Brin => 'BRIN',
        };
    }
}
