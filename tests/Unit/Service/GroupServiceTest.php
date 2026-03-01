<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Domain\Group;
use App\Domain\User;
use App\Exception\ForbiddenException;
use App\Exception\NotFoundException;
use App\Repository\GroupRepository;
use App\Repository\MembershipRepository;
use App\Service\GroupService;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GroupServiceTest extends TestCase
{
    private GroupRepository&MockObject $groupRepository;
    private MembershipRepository&MockObject $membershipRepository;
    private GroupService $service;

    protected function setUp(): void
    {
        $this->groupRepository = $this->createMock(GroupRepository::class);
        $this->membershipRepository = $this->createMock(MembershipRepository::class);
        $this->service = new GroupService($this->groupRepository, $this->membershipRepository);
    }

    // createGroup

    public function test_createGroup_creates_group_and_adds_creator_as_member(): void
    {
        $creator = $this->makeUser(id: 1);
        $group = $this->makeGroup(id: 10);

        $this->groupRepository
            ->expects($this->once())
            ->method('create')
            ->with('General')
            ->willReturn($group);

        $this->membershipRepository
            ->expects($this->once())
            ->method('add')
            ->with(10, 1);

        $result = $this->service->createGroup('General', $creator);

        $this->assertSame($group, $result);
    }

    // joinGroup

    public function test_joinGroup_throws_NotFoundException_when_group_does_not_exist(): void
    {
        $this->groupRepository->method('findById')->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->joinGroup($this->makeUser(), 99);
    }

    public function test_joinGroup_does_not_add_membership_when_already_a_member(): void
    {
        $this->groupRepository->method('findById')->willReturn($this->makeGroup());
        $this->membershipRepository->method('exists')->willReturn(true);

        $this->membershipRepository->expects($this->never())->method('add');

        $this->service->joinGroup($this->makeUser(), 1);
    }

    public function test_joinGroup_adds_membership_when_not_yet_a_member(): void
    {
        $user = $this->makeUser(id: 2);
        $group = $this->makeGroup(id: 5);

        $this->groupRepository->method('findById')->willReturn($group);
        $this->membershipRepository->method('exists')->willReturn(false);

        $this->membershipRepository
            ->expects($this->once())
            ->method('add')
            ->with(5, 2);

        $this->service->joinGroup($user, 5);
    }

    // ensureMember

    public function test_ensureMember_throws_NotFoundException_when_group_does_not_exist(): void
    {
        $this->groupRepository->method('findById')->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->ensureMember($this->makeUser(), 99);
    }

    public function test_ensureMember_throws_ForbiddenException_when_not_a_member(): void
    {
        $this->groupRepository->method('findById')->willReturn($this->makeGroup());
        $this->membershipRepository->method('exists')->willReturn(false);

        $this->expectException(ForbiddenException::class);

        $this->service->ensureMember($this->makeUser(), 1);
    }

    public function test_ensureMember_passes_when_user_is_a_member(): void
    {
        $this->groupRepository->method('findById')->willReturn($this->makeGroup());
        $this->membershipRepository->method('exists')->willReturn(true);

        $this->expectNotToPerformAssertions();

        $this->service->ensureMember($this->makeUser(), 1);
    }

    private function makeUser(int $id = 1, string $username = 'alice'): User
    {
        return new User($id, $username, 'tok', new DateTimeImmutable());
    }

    private function makeGroup(int $id = 1, string $name = 'General'): Group
    {
        return new Group($id, $name, new DateTimeImmutable());
    }
}
