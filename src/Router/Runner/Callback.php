<?php

namespace Jasny\Router\Runner;

use Jasny\Router\Runner;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Description of Callback
 *
 * @author arnold
 */
class Callback extends Runner
{
    /**
     * Use function to handle request and response
     * 
     * @param ServerRequestInterface  $request
     * @param ResponseInterface $response
     * @return ResponseInterface|mixed
     */
    public function run(ServerRequestInterface $request, ResponseInterface $response)
    {
        $route = $request->getAttribute('route');
        $callback = !empty($route->fn) ? $route->fn : null;

        if (!is_callable($callback)) {
            throw new \RuntimeException("'fn' property of route shoud be a callable");
        }

        return $callback($request, $response);
    }
}
