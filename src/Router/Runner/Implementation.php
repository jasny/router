<?php

namespace Jasny\Router\Runner;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Jasny\Router\Helper\NotFound;

/**
 * Common logic for invoking a runner
 */
trait Implementation
{
    use NotFound;
    
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
