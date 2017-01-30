<?php

namespace Jasny\Router\Middleware;

use Jasny\RouterInterface;
use Jasny\HttpMessage\GlobalEnvironmentInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Route to error page on error
 */
class ErrorPage
{    
    /**
     * Router
     * @var Router
     */
    protected $router = null;

    /**
     * Class constructor
     * 
     * @param RouterInterface $router
     */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * Get router connected to middleware
     *
     * @return RouterInterface
     */
    public function getRouter()
    {
        return $this->router;
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
        if ($next && !is_callable($next)) {
            throw new \InvalidArgumentException("next should be callable");            
        }

        $nextResponse = $next($request, $response); 
        
        return $this->isErrorStatus($nextResponse->getStatusCode())
            ? $this->routeToErrorPage($this->reviveRequest($request), $nextResponse)
            : $nextResponse;
    }

    /**
     * Revive a stale request
     * 
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface
     */
    protected function reviveRequest(ServerRequestInterface $request)
    {
        $isStale = interface_exists('Jasny\HttpMessage\GlobalEnvironmentInterface') &&
            $request instanceof GlobalEnvironmentInterface &&
            $request->isStale();
        
        return $isStale ? $request->revive() : $request;
    }
    
    /**
     * Get the route to the error page
     * 
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return Router\Route
     */
    protected function getErrorRoute(ServerRequestInterface $request, ResponseInterface $response)
    {
        if (
            interface_exists('Jasny\HttpMessage\GlobalEnvironmentInterface') &&
            $request instanceof GlobalEnvironmentInterface
        ) {
            $request = $request->withoutGlobalEnvironment();
        }
        
        $status = $response->getStatusCode();
        
        $errorUri = $request->getUri()
            ->withPath($status)
            ->withQuery(null)
            ->withFragment(null);
        
        return $this->router->getRoutes()->getRoute($request->withUri($errorUri));
    }
    
    /**
     * Route to the error page
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return ResponseInterface
     */
    protected function routeToErrorPage(ServerRequestInterface $request, ResponseInterface $response)
    {
        $errorRoute = $this->getErrorRoute($request, $response);
        
        if (!isset($errorRoute)) {
            return $response;
        }
        
        $runner = $this->router->getRunner();
        
        return $runner($request->withAttribute('route', $errorRoute), $response);
    }

    /**
     * Detect if response has error status code
     *
     * @param int $status
     * @return boolean
     */
    protected function isErrorStatus($status)
    {
        return $status >= 400;
    }
}
