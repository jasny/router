<?php

namespace Jasny;

use Jasny\Router\Routes;
use Jasny\Router\RunnerFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

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
     * Middlewares actions
     * @var array
     **/
    protected $middlewares = [];

    /**
     * Factory of Runner objects
     * @var RunnerFactory
     **/
    protected $factory;
    
    
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
     * Get a all routes
     * 
     * @return Routes
     */
    public function getRoutes()
    {
        return $this->routes;
    }    

    /**
     * Get all middlewares
     *
     * @return array
     */
    public function getMiddlewares()
    {
        return $this->middlewares;
    }

    
    /**
     * Get factory of Runner objects
     *
     * @return RunnerFactory
     */
    public function getFactory()
    {
        if (!isset($this->factory)) {
            $this->factory = new RunnerFactory();
        }
        
        return $this->factory;
    }

    /**
     * Set the factory of Runner objects
     *
     * @param callable $factory
     * @return Router $this
     */
    public function setFactory($factory)
    {
        if (!is_callable($factory)) {
            throw new \InvalidArgumentException("Factory must be a callable");            
        }

        $this->factory = $factory;

        return $this;
    }

    /**
     * Add middleware call to router
     *
     * @param callback $middleware
     * @return Router $this
     */
    public function add($middleware)
    {
        if (!is_callable($middleware)) {
            throw new \InvalidArgumentException("Middleware should be callable");
        }

        $this->middlewares[] = $middleware;

        return $this;
    }
    
    
    /**
     * Run the action for the request
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return ResponseInterface
     */
    final public function handle(ServerRequestInterface $request, ResponseInterface $response)
    {
        return $this->__invoke($request, $response);
    }

    /**
     * Run the action for the request (optionally as middleware), previously running middlewares, if any
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callback               $next
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next = null)
    {
        if (empty($this->middlewares)) {
            return $this->run($request, $response, $next);
        }
        
        $stack = array_merge([[$this, 'run']], $this->middlewares);

        // Turn the stack into a call chain
        foreach ($stack as $handle) {
            $next = function(ServerRequestInterface $request, ResponseInterface $response) use ($handle, $next) {
                return $handle($request, $response, $next);
            };
        }

        return $next($request, $response);
    }

    /**
     * Run the action
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callback      $next
     * @return ResponseInterface
     */
    public function run(ServerRequestInterface $request, ResponseInterface $response, $next = null)
    {
        $route = $this->routes->getRoute($request);
        
        if (!$route) {
            return $this->notFound($request, $response);
        }
        
        $requestWithRoute = $request->withAttribute('route', $route);
        
        $factory = $this->getFactory();
        $runner = $factory($route);

        return $runner($requestWithRoute, $response, $next);
    }

    /**
     * Return 'Not Found' response
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return ResponseInterface 
     */
    protected function notFound(ServerRequestInterface $request, ResponseInterface $response)
    {
        $notFound = $response->withStatus(404);
        $notFound->getBody()->write('Not Found');

        return $notFound;
    }
}
