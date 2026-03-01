<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use Tests\Integration\HttpTestCase;

class MessageEndpointTest extends HttpTestCase
{
    private int $userId;
    private int $groupId;
    private string $sendMessageUrl;
    private string $listMessagesUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = $this->seedUser('alice', 'token-alice');
        $this->groupId = $this->seedGroup('General');
        $this->seedMembership($this->groupId, $this->userId);

        $this->sendMessageUrl = "/groups/{$this->groupId}/messages";
        $this->listMessagesUrl = "/groups/{$this->groupId}/messages";
    }

    // POST /groups/{id}/messages
    
    public function test_send_returns_201_with_message_data(): void
    {
        $response = $this->post(
            $this->sendMessageUrl,
            ['message_text' => 'Hello'],
            'token-alice',
        );

        $this->assertSame(201, $response->getStatusCode());

        $body = $this->json($response);
        $this->assertSame('Hello', $body['message_text']);
        $this->assertSame($this->groupId, $body['group_id']);
        $this->assertSame($this->userId, $body['user_id']);
        $this->assertSame('alice', $body['username_snapshot']);
        $this->assertArrayHasKey('id', $body);
        $this->assertArrayHasKey('created_at', $body);
    }

    public function test_send_returns_400_when_message_text_is_empty(): void
    {
        $response = $this->post(
            $this->sendMessageUrl,
            ['message_text' => ''],
            'token-alice',
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_send_returns_403_when_user_is_not_a_member(): void
    {
        $this->seedUser('bob', 'token-bob');

        $response = $this->post(
            $this->sendMessageUrl,
            ['message_text' => 'Hello'],
            'token-bob',
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_send_returns_404_when_group_does_not_exist(): void
    {
        $response = $this->post('/groups/999/messages', ['message_text' => 'Hello'], 'token-alice');

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_send_returns_400_when_message_text_is_missing(): void
    {
        $response = $this->post($this->sendMessageUrl, [], 'token-alice');

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_send_returns_400_when_message_text_is_whitespace(): void
    {
        $response = $this->post(
            $this->sendMessageUrl,
            ['message_text' => '   '],
            'token-alice',
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_send_returns_401_with_invalid_token(): void
    {
        $response = $this->post($this->sendMessageUrl, ['message_text' => 'Hello'], 'invalid');

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_send_returns_401_without_token(): void
    {
        $response = $this->post($this->sendMessageUrl, ['message_text' => 'Hello']);

        $this->assertSame(401, $response->getStatusCode());
    }

    // GET /groups/{id}/messages

    public function test_list_returns_200_with_array_of_messages(): void
    {
        $this->post($this->sendMessageUrl, ['message_text' => 'First'], 'token-alice');
        $this->post($this->sendMessageUrl, ['message_text' => 'Second'], 'token-alice');

        $response = $this->get($this->listMessagesUrl, [], 'token-alice');

        $this->assertSame(200, $response->getStatusCode());

        $body = $this->json($response);
        $this->assertCount(2, $body);
        $this->assertSame('First', $body[0]['message_text']);
        $this->assertSame('Second', $body[1]['message_text']);
    }

    public function test_list_respects_after_id_cursor(): void
    {
        $r1 = $this->post($this->sendMessageUrl, ['message_text' => 'First'], 'token-alice');
        $this->post($this->sendMessageUrl, ['message_text' => 'Second'], 'token-alice');
        $this->post($this->sendMessageUrl, ['message_text' => 'Third'], 'token-alice');

        $firstId = $this->json($r1)['id'];

        $response = $this->get(
            $this->listMessagesUrl,
            ['after_id' => $firstId],
            'token-alice',
        );

        $body = $this->json($response);
        $this->assertCount(2, $body);
        $this->assertSame('Second', $body[0]['message_text']);
        $this->assertSame('Third', $body[1]['message_text']);
    }

    public function test_list_caps_limit_at_100(): void
    {
        // Insert 5 messages and request limit=999 — should still return all 5 without error
        for ($i = 1; $i <= 5; $i++) {
            $this->post($this->sendMessageUrl, ['message_text' => "Msg $i"], 'token-alice');
        }

        $response = $this->get(
            $this->listMessagesUrl,
            ['limit' => 999],
            'token-alice',
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(5, $this->json($response));
    }

    public function test_list_returns_403_when_user_is_not_a_member(): void
    {
        $this->seedUser('bob', 'token-bob');

        $response = $this->get($this->listMessagesUrl, [], 'token-bob');

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_list_returns_404_when_group_does_not_exist(): void
    {
        $response = $this->get('/groups/999/messages', [], 'token-alice');

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_list_returns_401_with_invalid_token(): void
    {
        $response = $this->get($this->listMessagesUrl, [], 'invalid');

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_list_returns_401_without_token(): void
    {
        $response = $this->get($this->listMessagesUrl);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_list_default_limit_returns_50_when_more_exist(): void
    {
        for ($i = 1; $i <= 120; $i++) {
            $this->post($this->sendMessageUrl, ['message_text' => "Msg $i"], 'token-alice');
        }

        $response = $this->get($this->listMessagesUrl, [], 'token-alice');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(50, $this->json($response));
    }

    public function test_list_limit_999_is_clamped_to_100(): void
    {
        for ($i = 1; $i <= 120; $i++) {
            $this->post($this->sendMessageUrl, ['message_text' => "Msg $i"], 'token-alice');
        }

        $response = $this->get($this->listMessagesUrl, ['limit' => 999], 'token-alice');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(100, $this->json($response));
    }

    public function test_list_after_id_100_returns_remaining_messages(): void
    {
        $ids = [];
        for ($i = 1; $i <= 120; $i++) {
            $r = $this->post($this->sendMessageUrl, ['message_text' => "Msg $i"], 'token-alice');
            $ids[] = $this->json($r)['id'];
        }

        $afterId = $ids[99]; // the 100th message id

        $response = $this->get(
            $this->listMessagesUrl,
            ['after_id' => $afterId, 'limit' => 100],
            'token-alice',
        );

        $body = $this->json($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(20, $body);
        $this->assertSame('Msg 101', $body[0]['message_text']);
    }

    public function test_list_after_id_beyond_highest_returns_empty_array(): void
    {
        $this->post($this->sendMessageUrl, ['message_text' => 'Only'], 'token-alice');

        $response = $this->get(
            $this->listMessagesUrl,
            ['after_id' => 999999],
            'token-alice',
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $this->json($response));
    }
}
