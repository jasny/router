<?php

namespace Jasny\Router;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Collection of routes
 */
interface RoutesInterface
{
    /**
     * Check if a route for the request exists
     * 
     * @param ServerRequestInterface $request
     * @return boolean
     */
    public function hasRoute(ServerRequestInterface $request);
    
    /**
     * Get route for the request
     * 
     * @param ServerRequestInterface $request
     * @return Route
     */
    public function getRoute(ServerRequestInterface $request);
}
