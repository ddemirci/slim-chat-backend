<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Domain\Message;
use App\Repository\MessageRepository;
use Tests\Integration\DatabaseTestCase;

class MessageRepositoryTest extends DatabaseTestCase
{
    private MessageRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new MessageRepository($this->pdo);

        $this->pdo->exec("INSERT INTO users (username, token) VALUES ('alice', 'tok-1')");
        $this->pdo->exec("INSERT INTO groups (name) VALUES ('General')");
    }

    public function test_create_returns_hydrated_message(): void
    {
        $message = $this->repo->create(1, 1, 'alice', 'Hello');

        $this->assertInstanceOf(Message::class, $message);
        $this->assertGreaterThan(0, $message->id);
        $this->assertSame(1, $message->groupId);
        $this->assertSame(1, $message->userId);
        $this->assertSame('alice', $message->usernameSnapshot);
        $this->assertSame('Hello', $message->messageText);
        $this->assertNotNull($message->createdAt);
    }

    public function test_create_allows_null_user_id(): void
    {
        $message = $this->repo->create(1, null, 'deleted-user', 'Hello');

        $this->assertNull($message->userId);
        $this->assertSame('deleted-user', $message->usernameSnapshot);
    }

    public function test_findByGroupAfterId_returns_messages_in_asc_order(): void
    {
        $this->repo->create(1, 1, 'alice', 'First');
        $this->repo->create(1, 1, 'alice', 'Second');
        $this->repo->create(1, 1, 'alice', 'Third');

        $messages = $this->repo->findByGroupAfterId(1, null, 50);

        $this->assertCount(3, $messages);
        $this->assertSame('First', $messages[0]->messageText);
        $this->assertSame('Second', $messages[1]->messageText);
        $this->assertSame('Third', $messages[2]->messageText);
    }

    public function test_findByGroupAfterId_respects_after_id_cursor(): void
    {
        $first = $this->repo->create(1, 1, 'alice', 'First');
        $this->repo->create(1, 1, 'alice', 'Second');
        $this->repo->create(1, 1, 'alice', 'Third');

        $messages = $this->repo->findByGroupAfterId(1, $first->id, 50);

        $this->assertCount(2, $messages);
        $this->assertSame('Second', $messages[0]->messageText);
        $this->assertSame('Third', $messages[1]->messageText);
    }

    public function test_findByGroupAfterId_respects_limit(): void
    {
        $this->repo->create(1, 1, 'alice', 'First');
        $this->repo->create(1, 1, 'alice', 'Second');
        $this->repo->create(1, 1, 'alice', 'Third');

        $messages = $this->repo->findByGroupAfterId(1, null, 2);

        $this->assertCount(2, $messages);
    }

    public function test_findByGroupAfterId_returns_only_messages_for_given_group(): void
    {
        $this->pdo->exec("INSERT INTO groups (name) VALUES ('Other')");

        $this->repo->create(1, 1, 'alice', 'In General');
        $this->repo->create(2, 1, 'alice', 'In Other');

        $messages = $this->repo->findByGroupAfterId(1, null, 50);

        $this->assertCount(1, $messages);
        $this->assertSame('In General', $messages[0]->messageText);
    }

    public function test_findByGroupAfterId_returns_empty_array_when_no_messages(): void
    {
        $messages = $this->repo->findByGroupAfterId(1, null, 50);

        $this->assertSame([], $messages);
    }
}
