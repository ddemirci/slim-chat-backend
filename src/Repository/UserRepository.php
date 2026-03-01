<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\User;
use DateTimeImmutable;
use PDO;

class UserRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function create(string $username, string $token): User
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, token, created_at) VALUES (:username, :token, CURRENT_TIMESTAMP)'
        );

        $stmt->execute([
            ':username' => $username,
            ':token' => $token,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        $user = $this->findById($id);

        if ($user === null) {
            throw new \RuntimeException("Failed to retrieve user after insert (id: $id).");
        }

        return $user;
    }

    public function findByToken(string $token): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, username, token, created_at FROM users WHERE token = :token LIMIT 1'
        );

        $stmt->execute([':token' => $token]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, username, token, created_at FROM users WHERE id = :id LIMIT 1'
        );

        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    private function hydrate(array $row): User
    {
        return new User(
            id: (int) $row['id'],
            username: $row['username'],
            token: $row['token'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
