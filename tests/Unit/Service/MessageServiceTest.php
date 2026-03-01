<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Domain\Message;
use App\Domain\User;
use App\Exception\ForbiddenException;
use App\Repository\MessageRepository;
use App\Service\GroupService;
use App\Service\MessageService;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MessageServiceTest extends TestCase
{
    private MessageRepository&MockObject $messageRepository;
    private GroupService&MockObject $groupService;
    private MessageService $service;

    protected function setUp(): void
    {
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->groupService = $this->createMock(GroupService::class);
        $this->service = new MessageService($this->messageRepository, $this->groupService);
    }

    // sendMessage

    public function test_sendMessage_checks_membership_before_creating(): void
    {
        $user = $this->makeUser(id: 1);

        $this->groupService
            ->expects($this->once())
            ->method('ensureMember')
            ->with($user, 5);

        $this->messageRepository
            ->method('create')
            ->willReturn($this->makeMessage());

        $this->service->sendMessage($user, 5, 'Hello');
    }

    public function test_sendMessage_propagates_ForbiddenException_from_ensureMember(): void
    {
        $this->groupService
            ->method('ensureMember')
            ->willThrowException(new ForbiddenException());

        $this->messageRepository->expects($this->never())->method('create');

        $this->expectException(ForbiddenException::class);

        $this->service->sendMessage($this->makeUser(), 1, 'Hello');
    }

    public function test_sendMessage_passes_correct_arguments_to_repository(): void
    {
        $user = $this->makeUser(id: 3, username: 'bob');
        $message = $this->makeMessage();

        $this->messageRepository
            ->expects($this->once())
            ->method('create')
            ->with(7, 3, 'bob', 'Hello world')
            ->willReturn($message);

        $result = $this->service->sendMessage($user, 7, 'Hello world');

        $this->assertSame($message, $result);
    }

    // listMessages

    public function test_listMessages_checks_membership_before_fetching(): void
    {
        $user = $this->makeUser(id: 1);

        $this->groupService
            ->expects($this->once())
            ->method('ensureMember')
            ->with($user, 5);

        $this->messageRepository->method('findByGroupAfterId')->willReturn([]);

        $this->service->listMessages($user, 5, null, 50);
    }

    public function test_listMessages_propagates_ForbiddenException_from_ensureMember(): void
    {
        $this->groupService
            ->method('ensureMember')
            ->willThrowException(new ForbiddenException());

        $this->messageRepository->expects($this->never())->method('findByGroupAfterId');

        $this->expectException(ForbiddenException::class);

        $this->service->listMessages($this->makeUser(), 1, null, 50);
    }

    public function test_listMessages_caps_limit_at_100(): void
    {
        $this->messageRepository
            ->expects($this->once())
            ->method('findByGroupAfterId')
            ->with(1, null, 100)
            ->willReturn([]);

        $this->service->listMessages($this->makeUser(), 1, null, 999);
    }

    public function test_listMessages_passes_after_id_to_repository(): void
    {
        $this->messageRepository
            ->expects($this->once())
            ->method('findByGroupAfterId')
            ->with(1, 42, 50)
            ->willReturn([]);

        $this->service->listMessages($this->makeUser(), 1, 42, 50);
    }

    public function test_listMessages_returns_messages_from_repository(): void
    {
        $messages = [$this->makeMessage(), $this->makeMessage(id: 2)];

        $this->messageRepository->method('findByGroupAfterId')->willReturn($messages);

        $result = $this->service->listMessages($this->makeUser(), 1, null, 50);

        $this->assertSame($messages, $result);
    }

    private function makeUser(int $id = 1, string $username = 'alice'): User
    {
        return new User($id, $username, 'tok', new DateTimeImmutable());
    }

    private function makeMessage(int $id = 1): Message
    {
        return new Message($id, 1, 1, 'alice', 'Hello', new DateTimeImmutable());
    }
}
