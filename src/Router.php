<?php

namespace Jasny;

use Jasny\RouterInterface;
use Jasny\Router\Routes;
use Jasny\Router\Route;
use Jasny\Router\Runner;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Route pretty URLs to correct controller
 */
class Router implements RouterInterface
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
     * @var callable
     **/
    protected $runner;
    
    
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
     * Get Runner
     *
     * @return callable
     */
    public function getRunner()
    {
        if (!isset($this->runner)) {
            $this->runner = new Runner\Delegate();
        }
        
        return $this->runner;
    }

    /**
     * Set Runner
     *
     * @param callable $runner
     * @return Router $this
     */
    public function setRunner($runner)
    {
        if (!is_callable($runner)) {
            throw new \InvalidArgumentException("Runner must be a callable");            
        }

        $this->runner = $runner;

        return $this;
    }

    /**
     * Add middleware call to router.
     *
     * @param string   $path        Middleware is only applied for this path (including subdirectories), may be omitted
     * @param callable $middleware
     * @return Router $this
     */
    public function add($path, $middleware = null)
    {
        if (!isset($middleware)) {
            $middleware = $path;
            $path = null;
        }

        if (!empty($path) && !ctype_digit($path) && $path[0] !== '/') {
            trigger_error("Middleware path '$path' doesn't start with a '/'", E_USER_NOTICE);
        }
        
        if (!is_callable($middleware)) {
            throw new \InvalidArgumentException("Middleware should be callable");
        }

        if (!empty($path)) {
            $middleware = $this->wrapMiddleware($path, $middleware);
        }
        
        $this->middlewares[] = $middleware;
        
        return $this;
    }
    
    /**
     * Wrap middleware, so it's only applied to a specified path
     * 
     * @param string   $path
     * @param callable $middleware
     * @return callable
     */
    protected function wrapMiddleware($path, $middleware)
    {
        return function(
            ServerRequestInterface $request,
            ResponseInterface $response,
            $next
        ) use ($middleware, $path) {
            $uriPath = $request->getUri()->getPath();

            if ($uriPath === $path || strpos($uriPath, rtrim($path, '/') . '/') === 0) {
                $ret = $middleware($request, $response, $next);
            } else {
                $ret = $next($request, $response);
            }
            
            return $ret;
        };
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
        
        $stack = array_reverse(array_merge($this->middlewares, [[$this, 'run']]));

        // Turn the stack into a call chain
        foreach ($stack as $handle) {
            $next = function(ServerRequestInterface $request, ResponseInterface $response) use ($handle, $next) {
                return $handle($request, $response, $next);
            };
        }

        return $next($request, $response);
    }

    /**
     * Run the action.
     * This method doesn't call the middlewares, but only run the action.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callback      $next
     * @return ResponseInterface
     */
    public function run(ServerRequestInterface $request, ResponseInterface $response, $next = null)
    {
        if (!$request->getAttribute('route') instanceof Route) {
            $route = $this->routes->getRoute($request);

            if (!$route) {
                return $this->notFound($request, $response);
            }

            $request = $request->withAttribute('route', $route);
        }
        
        $runner = $this->getRunner();

        return $runner($request, $response, $next);
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
