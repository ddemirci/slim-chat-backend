<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\User;
use App\Exception\ValidationException;
use App\Repository\UserRepository;
use PDOException;
use Ramsey\Uuid\Uuid;

class UserService
{
    public function __construct(private readonly UserRepository $userRepository) {}

    public function createUser(string $username): User
    {
        $token = Uuid::uuid4()->toString();

        try {
            return $this->userRepository->create($username, $token);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE constraint failed: users.username')) {
                throw new ValidationException("Username '$username' is already taken.");
            }
            throw $e;
        }
    }

    public function getByToken(string $token): ?User
    {
        return $this->userRepository->findByToken($token);
    }
}
