<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\MembershipRepository;
use Tests\Integration\DatabaseTestCase;

class MembershipRepositoryTest extends DatabaseTestCase
{
    private MembershipRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new MembershipRepository($this->pdo);

        $this->pdo->exec("INSERT INTO users (username, token) VALUES ('alice', 'tok-1'), ('bob', 'tok-2')");
        $this->pdo->exec("INSERT INTO groups (name) VALUES ('General'), ('Random')");
    }

    public function test_add_inserts_membership(): void
    {
        $this->repo->add(1, 1);

        $this->assertTrue($this->repo->exists(1, 1));
    }

    public function test_add_is_idempotent_on_duplicate(): void
    {
        $this->repo->add(1, 1);
        $this->repo->add(1, 1); // must not throw

        $this->assertTrue($this->repo->exists(1, 1));
    }

    public function test_exists_returns_true_when_membership_exists(): void
    {
        $this->repo->add(1, 1);

        $this->assertTrue($this->repo->exists(1, 1));
    }

    public function test_exists_returns_false_when_no_membership(): void
    {
        $this->assertFalse($this->repo->exists(1, 1));
    }

    public function test_exists_is_scoped_to_group_and_user(): void
    {
        $this->repo->add(1, 1);

        $this->assertFalse($this->repo->exists(2, 1));
        $this->assertFalse($this->repo->exists(1, 2));
    }

    public function test_removeByUser_deletes_all_memberships_for_user(): void
    {
        $this->repo->add(1, 1);
        $this->repo->add(2, 1);
        $this->repo->add(1, 2);

        $this->repo->removeByUser(1);

        $this->assertFalse($this->repo->exists(1, 1));
        $this->assertFalse($this->repo->exists(2, 1));
        $this->assertTrue($this->repo->exists(1, 2));
    }
}
