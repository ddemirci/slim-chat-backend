<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

class Membership
{
    public function __construct(
        public readonly int $groupId,
        public readonly int $userId,
        public readonly DateTimeImmutable $joinedAt,
    ) {}
}
