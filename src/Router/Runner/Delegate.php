<?php

namespace Jasny\Router\Runner;

use Jasny\Router\Route;
use Jasny\Router\Runner;
use Jasny\Router\Helper\NotFound;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Delegate the request to another Runner instance
 */
class Delegate
{
    use NotFound;
    
    /**
     * Create Runner instance
     * 
     * @param Route $route
     * @return Runner
     */
    public function getRunner(Route $route)
    {
        if (isset($route->controller)) {
            $class = Runner\Controller::class;
        } elseif (isset($route->fn)) {
            $class = Runner\Callback::class;
        } elseif (isset($route->file)) {
            $class = Runner\PhpScript::class;
        } else {
            trigger_error("Route has neither 'controller', 'fn' or 'file' defined", E_USER_NOTICE);
            return null;
        }

        return new $class();
    }
    
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
        $route = $request->getAttribute('route');
        
        if ($route instanceof Route) {
            $runner = $this->getRunner($route);
        } else {
            trigger_error("Route on request isn't set", E_USER_NOTICE);
        }
        
        if (!isset($runner)) {
            return $this->notFound($request, $response);
        }
        
        return $runner($request, $response, $next);
    }
}
