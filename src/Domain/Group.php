<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

class Group
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly DateTimeImmutable $createdAt,
    ) {}
}
