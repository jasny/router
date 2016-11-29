<?php

namespace Jasny\Router\Middleware;

use Jasny\Router\Routes;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Determine the route at forehand, so it can be used by subsequent middleware
 */
class DetermineRoute
{    
    /**
     * Routes
     * @var Routes
     */
    protected $routes = null;

    
    /**
     * Class constructor
     * 
     * @param Routes $routes
     */
    public function __construct(Routes $routes)
    {
        $this->routes = $routes;
    }

    /**
     * Get routes
     *
     * @return Routes
     */
    public function getRoutes()
    {
        return $this->routes;
    }
    

    /**
     * Run middleware action
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callable               $next
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        if (!is_callable($next)) {
            throw new \InvalidArgumentException("next should be callable");            
        }

        $route = $this->routes->getRoute($request);
        $requestWithRoute = $request->withAttribute('route', $route);
        
        return $next($requestWithRoute, $response);
    }
}
