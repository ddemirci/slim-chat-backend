<?php

declare(strict_types=1);

use App\Controller\GroupController;
use App\Controller\MessageController;
use App\Controller\UserController;
use App\Middleware\AuthMiddleware;
use App\Repository\GroupRepository;
use App\Repository\MessageRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\GroupService;
use App\Service\MessageService;
use App\Service\UserService;
use DI\ContainerBuilder;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Psr7\Factory\ResponseFactory;

$builder = new ContainerBuilder();
$container = $builder->build();

//
// PSR-17 Factories
//
$container->set(ResponseFactoryInterface::class, fn() => new ResponseFactory());

//
// PDO (SQLite)
//
$container->set(PDO::class, function () {
    $pdo = new PDO('sqlite:' . __DIR__ . '/../database/database.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON;');
    return $pdo;
});

//
// Repositories
//
$container->set(UserRepository::class, fn($c) =>
    new UserRepository($c->get(PDO::class))
);

$container->set(GroupRepository::class, fn($c) =>
    new GroupRepository($c->get(PDO::class))
);

$container->set(MembershipRepository::class, fn($c) =>
    new MembershipRepository($c->get(PDO::class))
);

$container->set(MessageRepository::class, fn($c) =>
    new MessageRepository($c->get(PDO::class))
);

//
// Services
//
$container->set(UserService::class, fn($c) =>
    new UserService($c->get(UserRepository::class))
);

$container->set(GroupService::class, fn($c) =>
    new GroupService(
        $c->get(GroupRepository::class),
        $c->get(MembershipRepository::class),
    )
);

$container->set(MessageService::class, fn($c) =>
    new MessageService(
        $c->get(MessageRepository::class),
        $c->get(GroupService::class),
    )
);

//
// Middleware
//
$container->set(AuthMiddleware::class, fn($c) =>
    new AuthMiddleware(
        $c->get(UserRepository::class),
        $c->get(ResponseFactoryInterface::class),
    )
);

//
// Controllers
//
$container->set(UserController::class, fn($c) =>
    new UserController($c->get(UserService::class))
);

$container->set(GroupController::class, fn($c) =>
    new GroupController($c->get(GroupService::class))
);

$container->set(MessageController::class, fn($c) =>
    new MessageController($c->get(MessageService::class))
);

return $container;