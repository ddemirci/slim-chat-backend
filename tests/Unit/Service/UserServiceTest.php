<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Domain\User;
use App\Repository\UserRepository;
use App\Service\UserService;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private UserService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->service = new UserService($this->userRepository);
    }

    public function test_createUser_passes_username_and_uuid_token_to_repository(): void
    {
        $user = $this->makeUser();

        $this->userRepository
            ->expects($this->once())
            ->method('create')
            ->with(
                'alice',
                $this->matchesRegularExpression(
                    '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i'
                ),
            )
            ->willReturn($user);

        $result = $this->service->createUser('alice');

        $this->assertSame($user, $result);
    }

    public function test_createUser_generates_different_tokens_each_call(): void
    {
        $tokens = [];

        $this->userRepository
            ->method('create')
            ->willReturnCallback(function (string $username, string $token) use (&$tokens): User {
                $tokens[] = $token;
                return $this->makeUser(token: $token);
            });

        $this->service->createUser('alice');
        $this->service->createUser('bob');

        $this->assertCount(2, array_unique($tokens));
    }

    public function test_getByToken_delegates_to_repository(): void
    {
        $user = $this->makeUser();

        $this->userRepository
            ->expects($this->once())
            ->method('findByToken')
            ->with('some-token')
            ->willReturn($user);

        $result = $this->service->getByToken('some-token');

        $this->assertSame($user, $result);
    }

    public function test_getByToken_returns_null_when_not_found(): void
    {
        $this->userRepository
            ->method('findByToken')
            ->willReturn(null);

        $this->assertNull($this->service->getByToken('unknown'));
    }

    private function makeUser(int $id = 1, string $username = 'alice', string $token = 'tok'): User
    {
        return new User($id, $username, $token, new DateTimeImmutable());
    }
}
