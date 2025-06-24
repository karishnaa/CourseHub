<?php

use Slim\App;
use Slim\Csrf\Guard;
use Psr\Http\Message\ResponseFactoryInterface;

return function (App $app) {
    $container = $app->getContainer();
    $responseFactory = $container->get(ResponseFactoryInterface::class);

    $csrf = new Guard($responseFactory);
    $csrf->setPersistentTokenMode(true); // keep the same token across requests

    $app->add($csrf);
};
