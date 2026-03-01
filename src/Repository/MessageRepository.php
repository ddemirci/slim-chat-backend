<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Message;
use DateTimeImmutable;
use PDO;

class MessageRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function create(int $groupId, ?int $userId, string $usernameSnapshot, string $messageText): Message
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO messages (group_id, user_id, username_snapshot, message_text, created_at)
             VALUES (:group_id, :user_id, :username_snapshot, :message_text, CURRENT_TIMESTAMP)'
        );

        $stmt->execute([
            ':group_id' => $groupId,
            ':user_id' => $userId,
            ':username_snapshot' => $usernameSnapshot,
            ':message_text' => $messageText,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        $message = $this->findById($id);

        if ($message === null) {
            throw new \RuntimeException("Failed to retrieve message after insert (id: $id).");
        }

        return $message;
    }

    public function findByGroupAfterId(int $groupId, ?int $afterId, int $limit): array
    {
        if ($afterId !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT id, group_id, user_id, username_snapshot, message_text, created_at
                 FROM messages
                 WHERE group_id = :group_id AND id > :after_id
                 ORDER BY id ASC
                 LIMIT :limit'
            );

            $stmt->bindValue(':group_id', $groupId, PDO::PARAM_INT);
            $stmt->bindValue(':after_id', $afterId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT id, group_id, user_id, username_snapshot, message_text, created_at
                 FROM messages
                 WHERE group_id = :group_id
                 ORDER BY id ASC
                 LIMIT :limit'
            );

            $stmt->bindValue(':group_id', $groupId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }

        $stmt->execute();

        return array_map(
            fn(array $row) => $this->hydrate($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    private function findById(int $id): ?Message
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, group_id, user_id, username_snapshot, message_text, created_at
             FROM messages WHERE id = :id LIMIT 1'
        );

        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    private function hydrate(array $row): Message
    {
        return new Message(
            id: (int) $row['id'],
            groupId: (int) $row['group_id'],
            userId: $row['user_id'] !== null ? (int) $row['user_id'] : null,
            usernameSnapshot: $row['username_snapshot'],
            messageText: $row['message_text'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
