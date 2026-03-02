<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Unit\Fixture;

use DateTimeImmutable;

final readonly class Employee
{
    public function __construct(
        public string $id,
        public string $name,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $deletedAt,
    ) {
    }

    public function withDeletedAt(string $deletedAt = 'now'): self
    {
        return new self($this->id, $this->name, $this->createdAt, new DateTimeImmutable($deletedAt));
    }
}
