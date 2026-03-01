<?php

declare(strict_types=1);

use DI\Container;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Container yükle
$container = require __DIR__ . '/../config/container.php';

AppFactory::setContainer($container);

$app = AppFactory::create();

// JSON body parsing middleware
$app->addBodyParsingMiddleware();

// Error middleware (development için true)
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler(
    new \App\Handler\ErrorHandler($container->get(\Psr\Http\Message\ResponseFactoryInterface::class))
);

// Routes
(require __DIR__ . '/../config/routes.php')($app);

$app->run();