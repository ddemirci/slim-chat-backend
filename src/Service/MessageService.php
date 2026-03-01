<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Message;
use App\Domain\User;
use App\Repository\MessageRepository;

class MessageService
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly GroupService $groupService,
    ) {}

    public function sendMessage(User $user, int $groupId, string $messageText): Message
    {
        $this->groupService->ensureMember($user, $groupId);

        return $this->messageRepository->create($groupId, $user->id, $user->username, $messageText);
    }

    public function listMessages(User $user, int $groupId, ?int $afterId, int $limit): array
    {
        $this->groupService->ensureMember($user, $groupId);

        $limit = min($limit, 100);

        return $this->messageRepository->findByGroupAfterId($groupId, $afterId, $limit);
    }
}
