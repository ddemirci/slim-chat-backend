<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $token,
        public readonly DateTimeImmutable $createdAt,
    ) {}
}
