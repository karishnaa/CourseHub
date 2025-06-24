<?php
declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Interfaces\RouteParserInterface;
use Slim\Csrf\Guard;
use function DI\autowire;
use App\Middleware\AdminAuth;
use App\Controller\Student\ProgrammeController;
use App\Controller\Student\RegisterController;
use App\Controller\Student\StudentController;
use App\Controller\Student\ContactController;
use App\Controller\Student\LoginController;
use App\Model\UserModel; 

return [
    //
    // 0) PSR-17 ResponseFactory binding
    //
    ResponseFactoryInterface::class => autowire(ResponseFactory::class),

    //
    // 1) Slim\App via AppFactory (so it sees our ResponseFactoryInterface)
    //
    App::class => function (ContainerInterface $c): App {
        AppFactory::setContainer($c);
        return AppFactory::create();
    },

    //
    // 2) PDO service
    //
    \PDO::class => function () {
        return require __DIR__ . '/db_connection.php';
    },

    //
    // 3) Twig view
    //
    Twig::class => function (ContainerInterface $c): Twig {
        return Twig::create(
            __DIR__ . '/../src/templates',
            [
                'cache'            => false,
                'debug'            => true,
                'strict_variables' => true,
            ]
        );
    },

    //
    // 4) CSRF Guard (injecting the PSR-17 ResponseFactory)
    //
    Guard::class => function (ContainerInterface $c): Guard {
        $factory = $c->get(ResponseFactoryInterface::class);
        $guard = new Guard($factory);
        $guard->setPersistentTokenMode(true);
        return $guard;
    },

    //
    // 5) AdminAuth middleware
    //
    AdminAuth::class => function (ContainerInterface $c): AdminAuth {
        return new AdminAuth(
            $c->get(\PDO::class),
            $c->get(Twig::class)
        );
    },

    //
    // 6) Model UserModel service
    //
    UserModel::class => function (ContainerInterface $c): UserModel {
        return new UserModel($c->get(\PDO::class));
    },



    RegisterController::class => function (ContainerInterface $c): RegisterController {
        return new RegisterController(
            $c->get(\PDO::class)
        );
    },

    LoginController::class => function (ContainerInterface $c): LoginController {
        return new LoginController(
            $c->get(Twig::class),
            $c->get(UserModel::class),
            $c->get(Guard::class),
            $c->get(RouteParserInterface::class)
        );
    },

    StudentController::class => function (ContainerInterface $c): StudentController {
        return new StudentController(
            $c->get(\PDO::class),
            $c->get(\Slim\Views\Twig::class),
            $c->get(\Slim\Csrf\Guard::class),


        );
    },

    ProgrammeController::class => function (ContainerInterface $c): ProgrammeController {
        return new ProgrammeController(
            $c->get(\Slim\Views\Twig::class),
            $c->get(\PDO::class)
        );
    },

    ContactController::class => function (ContainerInterface $c): ContactController {
        return new ContactController(
            $c->get(\PDO::class),
            $c->get(Twig::class)
        );
    }

];
