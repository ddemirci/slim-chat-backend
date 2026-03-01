<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Group;
use DateTimeImmutable;
use PDO;

class GroupRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function create(string $name): Group
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO groups (name, created_at) VALUES (:name, CURRENT_TIMESTAMP)'
        );

        $stmt->execute([':name' => $name]);

        $id = (int) $this->pdo->lastInsertId();

        $group = $this->findById($id);

        if ($group === null) {
            throw new \RuntimeException("Failed to retrieve group after insert (id: $id).");
        }

        return $group;
    }

    public function findById(int $id): ?Group
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, created_at FROM groups WHERE id = :id LIMIT 1'
        );

        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    private function hydrate(array $row): Group
    {
        return new Group(
            id: (int) $row['id'],
            name: $row['name'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
