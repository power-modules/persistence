<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Integration\Fixture;

use DateTimeImmutable;

final readonly class Product
{
    /**
     * @param array<string, mixed>|null $metadata
     * @param array<string>|null $tags
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?array $metadata,
        public ?array $tags,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
