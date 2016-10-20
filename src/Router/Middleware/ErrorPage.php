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
     * @param Router $routes
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
     * @param callback               $next
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next = null)
    {
        if ($next && !is_callable($next)) {
            throw new \InvalidArgumentException("'next' should be a callback");            
        }

        $response = $next ? call_user_func($next, $request, $response) : $response;    
        $status = $response->getStatusCode();

        if (!$this->isErrorStatus($status)) return $response;

        $uri = $request->getUri()->withPath("/$status");
        $request = $request->withUri($uri, true);

        return $this->getRouter()->run($request, $response);
    }

    /**
     * Detect if response has error status code
     *
     * @param int $status
     * @return boolean
     */
    protected function isErrorStatus($status)
    {
        return $status >= 400 && $status < 600;
    }
}
