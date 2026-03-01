<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

class Message
{
    public function __construct(
        public readonly int $id,
        public readonly int $groupId,
        public readonly ?int $userId,
        public readonly string $usernameSnapshot,
        public readonly string $messageText,
        public readonly DateTimeImmutable $createdAt,
    ) {}
}
