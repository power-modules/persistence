<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Integration\Fixture;

final class Note
{
    public function __construct(
        public string $id,
        public string $title,
    ) {
    }
}
