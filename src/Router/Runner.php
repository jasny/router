<?php

namespace Jasny\Router;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Jasny\Router\Route;

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
     * @param ServerRequestInterface  $request
     * @param ResponseInterface $response
     * @param callback          $next      Callback for if runner is used as middleware
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next = null)
    {
        $response = $this->run($request, $response);

        if (isset($next)) {
            $response = call_user_func($next, $request, $response);
        }

        return $response;
    }

    /**
     * Factory method
     * 
     * @param Route $route
     * @return Runner
     * @throws \RuntimeException if the route is misconfigured
     */
    public static function create(Route $route)
    {
        if (isset($route->controller)) {
            $class = Runner\Controller::class;
        } elseif (isset($route->fn)) {
            $class = Runner\Callback::class;
        } elseif (isset($route->file)) {
            $class = Runner\PhpScript::class;
        } else {
            throw new \RuntimeException("Route has neither 'controller', 'fn' or 'file' defined");
        }

        return new $class();
    }
}

