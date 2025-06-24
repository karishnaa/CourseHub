<?php
namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Factory\ResponseFactory;

class StaffAuth implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (empty($_SESSION['staff_id'])) {
            $response = (new ResponseFactory())->createResponse();
            // redirect to your staff login route
            return $response->withHeader('Location', '/CourseHub/public/staff/login')
                ->withStatus(302);
        }
        return $handler->handle($request);
    }
}
