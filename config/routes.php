<?php

declare(strict_types=1);

use App\Controller\GroupController;
use App\Controller\MessageController;
use App\Controller\UserController;
use App\Middleware\AuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {

    $app->post('/users', UserController::class . ':create');

    $app->group('', function (RouteCollectorProxy $group): void {

        $group->post('/groups', GroupController::class . ':create');
        $group->post('/groups/{id}/join', GroupController::class . ':join');

        $group->post('/groups/{id}/messages', MessageController::class . ':send');
        $group->get('/groups/{id}/messages', MessageController::class . ':list');

    })->add(AuthMiddleware::class);

};
