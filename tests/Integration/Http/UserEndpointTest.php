<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use Ramsey\Uuid\Uuid;
use Tests\Integration\HttpTestCase;

class UserEndpointTest extends HttpTestCase
{
    public function test_post_users_returns_201_with_user_data(): void
    {
        $response = $this->post('/users', ['username' => 'alice']);

        $this->assertSame(201, $response->getStatusCode());

        $body = $this->json($response);
        $this->assertSame('alice', $body['username']);
        $this->assertArrayHasKey('id', $body);
        $this->assertArrayHasKey('token', $body);
        $this->assertArrayHasKey('created_at', $body);
        $this->assertTrue(Uuid::isValid($body['token']));
    }

    public function test_post_users_returns_400_when_username_missing(): void
    {
        $response = $this->post('/users', []);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertArrayHasKey('error', $this->json($response));
    }

    public function test_post_users_returns_400_when_username_is_blank(): void
    {
        $response = $this->post('/users', ['username' => '   ']);

        $this->assertSame(400, $response->getStatusCode());
    }
}
