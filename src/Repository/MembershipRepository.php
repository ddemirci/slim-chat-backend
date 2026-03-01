<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class MembershipRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function add(int $groupId, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT OR IGNORE INTO group_user (group_id, user_id, joined_at) VALUES (:group_id, :user_id, CURRENT_TIMESTAMP)'
        );

        $stmt->execute([
            ':group_id' => $groupId,
            ':user_id' => $userId,
        ]);
    }

    public function exists(int $groupId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM group_user WHERE group_id = :group_id AND user_id = :user_id LIMIT 1'
        );

        $stmt->execute([
            ':group_id' => $groupId,
            ':user_id' => $userId,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public function removeByUser(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM group_user WHERE user_id = :user_id'
        );

        $stmt->execute([':user_id' => $userId]);
    }
}
