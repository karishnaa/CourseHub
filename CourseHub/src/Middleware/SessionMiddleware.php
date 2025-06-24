<?php
namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class SessionMiddleware implements MiddlewareInterface
{
    public const ADMIN_SESSION_KEY = 'admin_auth';

    public function process(Request $request, Handler $handler): Response
    {
        // Start PHP session if not already active
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => true, // Enable if using HTTPS
                'use_strict_mode' => true
            ]);
        }

        return $handler->handle($request);
    }
}