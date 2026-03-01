<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Message;
use App\Service\MessageService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MessageController
{
    public function __construct(private readonly MessageService $messageService) {}

    public function send(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $groupId = (int) $args['id'];
        $user = $request->getAttribute('user');

        $body = (array) $request->getParsedBody();
        $messageText = trim((string) ($body['message_text'] ?? ''));

        if ($messageText === '') {
            $response->getBody()->write(json_encode(
                ['error' => 'message_text is required'],
                JSON_THROW_ON_ERROR,
            ));

            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $message = $this->messageService->sendMessage($user, $groupId, $messageText);

        $response->getBody()->write(json_encode(
            $this->serializeMessage($message),
            JSON_THROW_ON_ERROR,
        ));

        return $response
            ->withStatus(201)
            ->withHeader('Content-Type', 'application/json');
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $groupId = (int) $args['id'];
        $user = $request->getAttribute('user');

        $params = $request->getQueryParams();
        $afterId = isset($params['after_id']) && $params['after_id'] !== ''
            ? (int) $params['after_id']
            : null;
        
        $limit = isset($params['limit']) && (int)$params['limit'] > 0
            ? (int)$params['limit']
            : 50;

        $messages = $this->messageService->listMessages($user, $groupId, $afterId, $limit);

        $response->getBody()->write(json_encode(
            array_map($this->serializeMessage(...), $messages),
            JSON_THROW_ON_ERROR,
        ));

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json');
    }

    private function serializeMessage(Message $message): array
    {
        return [
            'id' => $message->id,
            'group_id' => $message->groupId,
            'user_id' => $message->userId,
            'username_snapshot' => $message->usernameSnapshot,
            'message_text' => $message->messageText,
            'created_at' => $message->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
