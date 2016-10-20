<?php

namespace Jasny\Router;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A runner can be invoked in order to run the action specified in a route
 */
abstract class Runner
{    
    /**
     * Invoke the action specified in the route
     * 
     * @param ServerRequestInterface  $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    abstract public function run(ServerRequestInterface $request, ResponseInterface $response);
    
    /**
     * Invoke the action specified in the route and call the next method
     * 
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callback               $next      Callback for if runner is used as middleware
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next = null)
    {
        $newResponse = $this->run($request, $response);

        if (isset($next)) {
            $newResponse = call_user_func($next, $request, $newResponse);
        }

        return $newResponse;
    }
}
