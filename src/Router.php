<?php

namespace Jasny;

use Jasny\Router\Routes;
use Psr7\Http\Message\ServerRequest;
use Psr7\Http\Message\Response;

/**
 * Route pretty URLs to correct controller
 */
class Router
{
    /**
     * Specific routes
     * @var Routes
     */
    protected $routes;
    
    
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
     * Get a list of all routes
     * 
     * @return object
     */
    public function getRoutes()
    {
        return $this->routes;
    }
    
    
    /**
     * Set the webroot subdir from DOCUMENT_ROOT.
     * 
     * @param string $dir
     * @return Router
     */
    public function setBase($dir)
    {
        $this->base = rtrim($dir, '/');
        $this->route = null;

        return $this;
    }

    /**
     * Get the webroot subdir from DOCUMENT_ROOT.
     * 
     * @return string
     */
    public function getBase()
    {
        return $this->base;
    }

    /**
     * Add a base path to the URL if the webroot isn't the same as the webservers document root
     * 
     * @param string $url
     * @return string
     */
    public function rebase($url)
    {
        return ($this->getBase() ?: '/') . ltrim($url, '/');
    }


    /**
     * Run the action for the request
     *
     * @param ServerRequest $request
     * @param Response      $response
     * @return Response
     */
    final public function run(ServerRequest $request, Response $response)
    {
        return $this->handle($request, $response);
    }

    /**
     * Run the action for the request (optionally as middleware)
     *
     * @param ServerRequest $request
     * @param Response      $response
     * @param callback      $next
     * @return Response
     */
    final public function __invoke(ServerRequest $request, Response $response, $next = null)
    {
        return $this->handle($request, $response, $next);
    }

    /**
     * Run the action
     *
     * @param ServerRequest $request
     * @param Response      $response
     * @param callback      $next
     * @return Response
     */
    protected function handle(ServerRequest $request, Response $response, $next = null)
    {
        // TODO find route and run.
        // TODO if not found -> 404
    }
}

