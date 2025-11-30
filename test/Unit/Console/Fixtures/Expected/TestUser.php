<?php

declare(strict_types=1);

namespace App\Domain;

class TestUser
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name,
        public readonly ?string $email,
        public readonly ?int $age,
        public readonly ?int $isActive,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }
}
