<?php

namespace Jasny;

use Jasny\Router\Runner\RunnerFactory;
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
     * Middlewares actions
     * @var array
     **/
    protected $middlewares = [];

    /**
     * Factory of Runner objects
     * @var RunnerFactory
     **/
    protected $factory = null;
    
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
     * Get middlewares
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
        return $this->factory ?: $this->factory = new RunnerFactory();
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
            throw new \InvalidArgumentException("Middleware should be a callable");
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
    final public function run(ServerRequestInterface $request, ResponseInterface $response)
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
        $handle = [$this, 'handle'];

        #Call to $this->handle will be executed last in the chain of middlewares
        $next = function(ServerRequestInterface $request, ResponseInterface $response) use ($next, $handle) {
            return call_user_func($handle, $request, $response, $next);
        };

        #Build middlewares call chain, so that the last added was executed in first place
        foreach ($this->middlewares as $middleware) {
            $next = function(ServerRequestInterface $request, ResponseInterface $response) use ($next, $middleware) {
                return $middleware($request, $response, $next);
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
    protected function handle(ServerRequestInterface $request, ResponseInterface $response, $next = null)
    {
        $glob = new Glob($this->routes);
        $route = $glob->getRoute($request);
        
        if (!$route) return $this->notFound($response);

        $request->withAttribute('route', $route);        
        $factory = $this->getFactory();
        $runner = $factory($route);

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

