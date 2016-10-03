<?php

namespace Jasny\Router;

use Psr\Http\Message\ServerRequestInterface as ServerRequest;

/**
 * Collection of routes
 */
interface Routes
{
    /**
     * Check if a route for the request exists
     * 
     * @param ServerRequest $request
     * @return boolean
     */
    public function hasRoute(ServerRequest $request);
    
    /**
     * Get route for the request
     * 
     * @param ServerRequest $request
     * @return Route
     */
    public function getRoute(ServerRequest $request);
}
