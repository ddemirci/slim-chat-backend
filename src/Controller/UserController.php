<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\UserService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserController
{
    public function __construct(private readonly UserService $userService) {}

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $username = trim((string) ($body['username'] ?? ''));

        if ($username === '') {
            $response->getBody()->write(json_encode(
                ['error' => 'username is required'],
                JSON_THROW_ON_ERROR,
            ));

            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $user = $this->userService->createUser($username);

        $response->getBody()->write(json_encode([
            'id' => $user->id,
            'username' => $user->username,
            'token' => $user->token,
            'created_at' => $user->createdAt->format('Y-m-d H:i:s'),
        ], JSON_THROW_ON_ERROR));

        return $response
            ->withStatus(201)
            ->withHeader('Content-Type', 'application/json');
    }
}
