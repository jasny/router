<?php

namespace Jasny\Router\Middleware;

use Jasny\Router;
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
     * @param Router $router
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Get router connected to middleware
     *
     * @return Router
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
            ? $this->routeToErrorPage($request, $nextResponse)
            : $nextResponse;
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
        $status = $response->getStatusCode();
        
        $uri = $request->getUri()
            ->withPath($status)
            ->withQuery(null)
            ->withFragment(null);
        
        $errorRequest = $request->withUri($uri);
        $errorRoute = $this->router->getRoutes()->getRoute($errorRequest);
        
        if (!isset($errorRoute)) {
            return $response;
        }
        
        $factory = $this->router->getFactory();
        $runner = $factory($errorRoute);
        
        // TODO: replace response body
        
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
