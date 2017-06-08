<?php

namespace Jasny\Router\Runner;

use Jasny\Router\Runner;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Use `fn` property of route as callback
 */
class Callback
{
    use Runner\Implementation;
    
    /**
     * Use function to handle request and response
     * 
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return ResponseInterface|mixed
     */
    public function run(ServerRequestInterface $request, ResponseInterface $response)
    {
        $route = $request->getAttribute('route');
        $callback = !empty($route->fn) ? $route->fn : null;

        if (!is_callable($callback)) {
            trigger_error("'fn' property of route shoud be a callable", E_USER_NOTICE);
            return $this->notFound($request, $response);
        }

        return $callback($request, $response);
    }
}
