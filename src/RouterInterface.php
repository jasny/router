<?php

namespace Jasny;

use Jasny\Router\RoutesInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Interface for a router.
 */
interface RouterInterface
{
    /**
     * Get a all routes
     * 
     * @return RoutesInterface
     */
    public function getRoutes();

    /**
     * Get Runner
     *
     * @return callable
     */
    public function getRunner();
    
    /**
     * Run the action for the request
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request, ResponseInterface $response);
    
    /**
     * Run the action.
     * This method doesn't call the middlewares, but only run the action.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callback      $next
     * @return ResponseInterface
     */
    public function run(ServerRequestInterface $request, ResponseInterface $response, $next = null);
}
