<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Domain\Group;
use App\Repository\GroupRepository;
use Tests\Integration\DatabaseTestCase;

class GroupRepositoryTest extends DatabaseTestCase
{
    private GroupRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new GroupRepository($this->pdo);
    }

    public function test_create_returns_hydrated_group(): void
    {
        $group = $this->repo->create('General');

        $this->assertInstanceOf(Group::class, $group);
        $this->assertGreaterThan(0, $group->id);
        $this->assertSame('General', $group->name);
        $this->assertNotNull($group->createdAt);
    }

    public function test_findById_returns_group_when_found(): void
    {
        $created = $this->repo->create('General');
        $found = $this->repo->findById($created->id);

        $this->assertNotNull($found);
        $this->assertSame($created->id, $found->id);
        $this->assertSame('General', $found->name);
    }

    public function test_findById_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repo->findById(999));
    }
}
