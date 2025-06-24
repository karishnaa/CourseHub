<?php

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;

class AdminAuth implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Do not call session_start() - session is already started in SessionMiddleware
        if (empty($_SESSION['admin_auth'])) {
            // Use Slim response redirect, not header()+exit
            $responseFactory = new \Slim\Psr7\Factory\ResponseFactory();
            $response = $responseFactory->createResponse();
            return $response
                ->withHeader('Location', '/CourseHub/public/admin/login')
                ->withStatus(302);
        }

        return $handler->handle($request);
    }
}
