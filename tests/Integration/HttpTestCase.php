<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Controller\GroupController;
use App\Controller\MessageController;
use App\Controller\UserController;
use App\Handler\ErrorHandler;
use App\Middleware\AuthMiddleware;
use App\Repository\GroupRepository;
use App\Repository\MembershipRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Service\GroupService;
use App\Service\MessageService;
use App\Service\UserService;
use DI\ContainerBuilder;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

abstract class HttpTestCase extends TestCase
{
    protected App $app;
    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec(
            (string) file_get_contents(__DIR__ . '/../../database/schema.sql')
        );

        $this->app = $this->buildApp();
    }

    private function buildApp(): App
    {
        $pdo = $this->pdo;

        $builder = new ContainerBuilder();
        $container = $builder->build();

        $container->set(PDO::class, fn() => $pdo);
        $container->set(ResponseFactoryInterface::class, fn() => new ResponseFactory());

        $container->set(UserRepository::class, fn($c) => new UserRepository($c->get(PDO::class)));
        $container->set(GroupRepository::class, fn($c) => new GroupRepository($c->get(PDO::class)));
        $container->set(MembershipRepository::class, fn($c) => new MembershipRepository($c->get(PDO::class)));
        $container->set(MessageRepository::class, fn($c) => new MessageRepository($c->get(PDO::class)));

        $container->set(UserService::class, fn($c) => new UserService($c->get(UserRepository::class)));
        $container->set(GroupService::class, fn($c) => new GroupService(
            $c->get(GroupRepository::class),
            $c->get(MembershipRepository::class),
        ));
        $container->set(MessageService::class, fn($c) => new MessageService(
            $c->get(MessageRepository::class),
            $c->get(GroupService::class),
        ));

        $container->set(AuthMiddleware::class, fn($c) => new AuthMiddleware(
            $c->get(UserRepository::class),
            $c->get(ResponseFactoryInterface::class),
        ));

        $container->set(UserController::class, fn($c) => new UserController($c->get(UserService::class)));
        $container->set(GroupController::class, fn($c) => new GroupController($c->get(GroupService::class)));
        $container->set(MessageController::class, fn($c) => new MessageController($c->get(MessageService::class)));

        AppFactory::setContainer($container);
        $app = AppFactory::create();

        $app->addBodyParsingMiddleware();

        $errorMiddleware = $app->addErrorMiddleware(false, false, false);
        $errorMiddleware->setDefaultErrorHandler(
            new ErrorHandler($container->get(ResponseFactoryInterface::class))
        );

        (require __DIR__ . '/../../config/routes.php')($app);

        return $app;
    }

    protected function post(string $uri, array $body = [], ?string $token = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', $uri)
            ->withParsedBody($body);

        if ($token !== null) {
            $request = $request->withHeader('X-User-Token', $token);
        }

        return $this->app->handle($request);
    }

    protected function get(string $uri, array $query = [], ?string $token = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', $uri)
            ->withQueryParams($query);

        if ($token !== null) {
            $request = $request->withHeader('X-User-Token', $token);
        }

        return $this->app->handle($request);
    }

    protected function json(ResponseInterface $response): array
    {
        return (array) json_decode((string) $response->getBody(), true);
    }

    protected function seedUser(string $username = 'alice', string $token = 'token-alice'): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, token) VALUES (:username, :token)'
        );
        $stmt->execute([':username' => $username, ':token' => $token]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function seedGroup(string $name = 'General'): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO groups (name) VALUES (:name)');
        $stmt->execute([':name' => $name]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function seedMembership(int $groupId, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO group_user (group_id, user_id) VALUES (:group_id, :user_id)'
        );
        $stmt->execute([':group_id' => $groupId, ':user_id' => $userId]);
    }
}
