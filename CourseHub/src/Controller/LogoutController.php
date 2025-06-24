<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

class LogoutController {
    public function logout(Request $request, Response $response): Response {
        // Tear down the session
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        session_unset();
        session_destroy();

        // Redirect to the student home page (named 'home')
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();
        $homeUrl     = $routeParser->urlFor('home');

        return $response
            ->withHeader('Location', $homeUrl)
            ->withStatus(302);
    }
}
