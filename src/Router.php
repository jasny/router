<?php

namespace Jasny;

use Jasny\Router\Runner;
use Jasny\Router\Routes\Glob;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Route pretty URLs to correct controller
 */
class Router
{
    /**
     * Specific routes
     * @var array
     */
    protected $routes = [];    
    
    /**
     * Class constructor
     * 
     * @param array $routes
     */
    public function __construct(array $routes)
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
     * Run the action for the request
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return ResponseInterface
     */
    final public function run(ServerRequestInterface $request, ResponseInterface $response)
    {
        return $this->__invoke($request, $response);
    }

    /**
     * Run the action for the request (optionally as middleware)
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callback      $next
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next = null)
    {
        return $this->handle($request, $response, $next);
    }

    /**
     * Run the action
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callback      $next
     * @return ResponseInterface
     */
    protected function handle(ServerRequestInterface $request, ResponseInterface $response, $next = null)
    {
        $glob = new Glob($this->routes);
        $route = $glob->getRoute($request);
        
        if (!$route) return $this->notFound($response);

        $runner = Runner::create($route);

        return $runner($request, $response, $next);
    }

    /**
     * Return 'Not Found' response
     *
     * @param ResponseInterface      $response
     * @return ResponseInterface 
     */
    protected function notFound(ResponseInterface $response)
    {
        $message = 'Not Found';            

        $body = $response->getBody();        
        $body->rewind();
        $body->write($message);

        return $response->withStatus(404, $message)->withBody($body);
    }
}

