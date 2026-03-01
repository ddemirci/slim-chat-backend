<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Repository\UserRepository;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $request->getHeaderLine('X-User-Token');

        if ($token === '') {
            return $this->unauthorized();
        }

        $user = $this->userRepository->findByToken($token);

        if ($user === null) {
            return $this->unauthorized();
        }

        return $handler->handle($request->withAttribute('user', $user));
    }

    private function unauthorized(): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(401)
            ->withHeader('Content-Type', 'application/json');

        $payload = json_encode([
            'error' => 'Unauthorized',
        ], JSON_THROW_ON_ERROR);

        $response->getBody()->write($payload);

        return $response;
    }
}
