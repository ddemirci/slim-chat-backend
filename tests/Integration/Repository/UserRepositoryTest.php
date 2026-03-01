<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Domain\User;
use App\Repository\UserRepository;
use Tests\Integration\DatabaseTestCase;

class UserRepositoryTest extends DatabaseTestCase
{
    private UserRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new UserRepository($this->pdo);
    }

    public function test_create_returns_hydrated_user(): void
    {
        $user = $this->repo->create('alice', 'tok-1');

        $this->assertInstanceOf(User::class, $user);
        $this->assertGreaterThan(0, $user->id);
        $this->assertSame('alice', $user->username);
        $this->assertSame('tok-1', $user->token);
        $this->assertNotNull($user->createdAt);
    }

    public function test_create_throws_on_duplicate_username(): void
    {
        $this->repo->create('alice', 'tok-1');

        $this->expectException(\Exception::class);

        $this->repo->create('alice', 'tok-2');
    }

    public function test_create_throws_on_duplicate_token(): void
    {
        $this->repo->create('alice', 'tok-1');

        $this->expectException(\Exception::class);

        $this->repo->create('bob', 'tok-1');
    }

    public function test_findByToken_returns_user_when_found(): void
    {
        $created = $this->repo->create('alice', 'tok-1');
        $found = $this->repo->findByToken('tok-1');

        $this->assertNotNull($found);
        $this->assertSame($created->id, $found->id);
        $this->assertSame('alice', $found->username);
    }

    public function test_findByToken_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repo->findByToken('unknown'));
    }

    public function test_findById_returns_user_when_found(): void
    {
        $created = $this->repo->create('alice', 'tok-1');
        $found = $this->repo->findById($created->id);

        $this->assertNotNull($found);
        $this->assertSame($created->id, $found->id);
    }

    public function test_findById_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repo->findById(999));
    }
}
