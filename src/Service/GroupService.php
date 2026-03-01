<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Group;
use App\Domain\User;
use App\Exception\ForbiddenException;
use App\Exception\NotFoundException;
use App\Repository\GroupRepository;
use App\Repository\MembershipRepository;

class GroupService
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly MembershipRepository $membershipRepository,
    ) {}

    public function createGroup(string $name, User $creator): Group
    {
        $group = $this->groupRepository->create($name);

        $this->membershipRepository->add($group->id, $creator->id);

        return $group;
    }

    public function joinGroup(User $user, int $groupId): void
    {
        $group = $this->groupRepository->findById($groupId);

        if ($group === null) {
            throw new NotFoundException("Group $groupId not found.");
        }

        if ($this->membershipRepository->exists($groupId, $user->id)) {
            return;
        }

        $this->membershipRepository->add($groupId, $user->id);
    }

    public function ensureMember(User $user, int $groupId): void
    {
        $group = $this->groupRepository->findById($groupId);

        if ($group === null) {
            throw new NotFoundException("Group $groupId not found.");
        }

        if (!$this->membershipRepository->exists($groupId, $user->id)) {
            throw new ForbiddenException("User {$user->id} is not a member of group $groupId.");
        }
    }
}
