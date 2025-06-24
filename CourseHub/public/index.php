<?php
declare(strict_types=1);

session_start();

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Slim\Interfaces\RouteParserInterface;

// 1) Autoload
require __DIR__ . '/../vendor/autoload.php';


$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../config/dependencies.php');
$container = $containerBuilder->build();

// 3) Set container on AppFactory & create App
AppFactory::setContainer($container);
$app = AppFactory::create();



// Add SessionMiddleware (must come BEFORE TwigMiddleware)
$app->add(new App\Middleware\SessionMiddleware());
$app->add($container->get(\Slim\Csrf\Guard::class));
// Then add TwigMiddleware (as you already have)
$app->add(TwigMiddleware::create($app, $container->get(Twig::class)));

// 4) (Optional) If not at web-root, set base path
$app->setBasePath('/CourseHub/public');

// 5) Add Slim routing & error middleware
$app->addRoutingMiddleware();
$app->addErrorMiddleware(
    true,   // displayErrorDetails (dev only!)
    true,   // logErrors
    true    // logErrorDetails
);



// 7) Bind RouteParserInterface so controllers can do urlFor(...)
$container->set(
    RouteParserInterface::class,
    function () use ($app): RouteParserInterface {
        return $app->getRouteCollector()->getRouteParser();
    }
);

// 8) Register any custom middleware (e.g. AdminAuth)
(require __DIR__ . '/../src/Middleware/admin.php')($app);

// 9) Register routes
$routes = require __DIR__ . '/../src/routes.php';
if (is_callable($routes)) {
    $routes($app);
} else {
    throw new RuntimeException('Routes file must return a callable.');
}

// 10) Run the Slim app
$app->run();
















