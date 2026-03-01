<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use Tests\Integration\HttpTestCase;

class GroupEndpointTest extends HttpTestCase
{
    public function test_post_groups_returns_201_with_group_data(): void
    {
        $userId = $this->seedUser('alice', 'token-alice');

        $response = $this->post('/groups', ['name' => 'General'], 'token-alice');

        $this->assertSame(201, $response->getStatusCode());

        $body = $this->json($response);
        $this->assertSame('General', $body['name']);
        $this->assertArrayHasKey('id', $body);
        $this->assertArrayHasKey('created_at', $body);
    }

    public function test_post_groups_creator_is_added_as_member(): void
    {
        $userId = $this->seedUser('alice', 'token-alice');

        $response = $this->post('/groups', ['name' => 'General'], 'token-alice');
        $groupId = $this->json($response)['id'];

        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM group_user WHERE group_id = :g AND user_id = :u'
        );
        $stmt->execute([':g' => $groupId, ':u' => $userId]);

        $this->assertNotFalse($stmt->fetchColumn());
    }

    public function test_post_groups_returns_400_when_name_missing(): void
    {
        $this->seedUser('alice', 'token-alice');

        $response = $this->post('/groups', [], 'token-alice');

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_post_groups_returns_401_without_token(): void
    {
        $response = $this->post('/groups', ['name' => 'General']);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_post_groups_returns_401_with_invalid_token(): void
    {
        $response = $this->post('/groups', ['name' => 'General'], 'invalid');

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_post_join_returns_204_when_non_member_joins(): void
    {
        $this->seedUser('alice', 'token-alice');
        $groupId = $this->seedGroup('General');

        $response = $this->post("/groups/$groupId/join", [], 'token-alice');

        $this->assertSame(204, $response->getStatusCode());
    }

    public function test_post_join_returns_204_when_already_a_member(): void
    {
        $userId = $this->seedUser('alice', 'token-alice');
        $groupId = $this->seedGroup('General');
        $this->seedMembership($groupId, $userId);

        $response = $this->post("/groups/$groupId/join", [], 'token-alice');

        $this->assertSame(204, $response->getStatusCode());
    }

    public function test_post_join_returns_404_when_group_does_not_exist(): void
    {
        $this->seedUser('alice', 'token-alice');

        $response = $this->post('/groups/999/join', [], 'token-alice');

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_post_groups_returns_400_when_name_is_blank(): void
    {
        $this->seedUser('alice', 'token-alice');

        $response = $this->post('/groups', ['name' => '   '], 'token-alice');

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_post_join_returns_401_without_token(): void
    {
        $groupId = $this->seedGroup();

        $response = $this->post("/groups/$groupId/join");

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_post_join_returns_401_with_invalid_token(): void
    {
        $groupId = $this->seedGroup();

        $response = $this->post("/groups/$groupId/join", [], 'invalid');

        $this->assertSame(401, $response->getStatusCode());
    }
}
