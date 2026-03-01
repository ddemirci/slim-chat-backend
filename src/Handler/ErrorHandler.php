<?php

declare(strict_types=1);

namespace App\Handler;

use App\Exception\ForbiddenException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Exception\HttpException;
use Throwable;

class ErrorHandler
{
    public function __construct(private readonly ResponseFactoryInterface $responseFactory) {}

    public function __invoke(
        \Psr\Http\Message\ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        $status = match (true) {
            $exception instanceof NotFoundException  => 404,
            $exception instanceof ForbiddenException => 403,
            $exception instanceof ValidationException => 400,
            $exception instanceof HttpException      => $exception->getCode(),
            default                                  => 500,
        };

        $payload = ['error' => $exception->getMessage()];

        if ($status === 500) {
            $payload['error'] = $displayErrorDetails
                ? $exception->getMessage()
                : 'Internal Server Error';
        }

        $response = $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json');

        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));

        return $response;
    }
}
