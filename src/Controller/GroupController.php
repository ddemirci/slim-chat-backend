<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\GroupService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GroupController
{
    public function __construct(private readonly GroupService $groupService) {}

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        
        $name = trim((string) ($body['name'] ?? ''));

        if ($name === '') {
            $response->getBody()->write(json_encode(
                ['error' => 'name is required'],
                JSON_THROW_ON_ERROR,
            ));

            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $user = $request->getAttribute('user');
        $group = $this->groupService->createGroup($name, $user);

        $response->getBody()->write(json_encode([
            'id' => $group->id,
            'name' => $group->name,
            'created_at' => $group->createdAt->format('Y-m-d H:i:s'),
        ], JSON_THROW_ON_ERROR));

        return $response
            ->withStatus(201)
            ->withHeader('Content-Type', 'application/json');
    }

    public function join(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $groupId = (int) $args['id'];
        $user = $request->getAttribute('user');

        $this->groupService->joinGroup($user, $groupId);

        return $response->withStatus(204);
    }
}
