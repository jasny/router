<?php

namespace Jasny\Router\Middleware;

use Jasny\Router\Routes;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Set response to 'not found' or 'method not allowed' if route is non exist 
 */
class NotFound
{    
    /**
     * Routes
     * @var Routes
     */
    protected $routes = null;

    /**
     * Action for 'not found' case
     * @var callback|int
     **/
    protected $notFound = null;

    /**
     * Action for 'method not allowed' case
     * @var callback|int
     **/
    protected $methodNotAllowed = null;

    /**
     * Class constructor
     * 
     * @param Routes $routes
     * @param callback|int $notFound 
     * @param callback|int $methodNotAllowed 
     */
    public function __construct(Routes $routes, $notFound = 404, $methodNotAllowed = null)
    {
        if (!(is_numeric($notFound) && $notFound >= 100 && $notFound <= 599) && !is_callable($notFound)) {
            throw new \InvalidArgumentException("'Not found' parameter should be a code in range 100-599 or a callback");
        }

        if ($methodNotAllowed && !(is_numeric($methodNotAllowed) && $methodNotAllowed >= 100 && $methodNotAllowed <= 599) && !is_callable($methodNotAllowed)) {
            throw new \InvalidArgumentException("'Method not allowed' parameter should be a code in range 100-599 or a callback");   
        }

        $this->routes = $routes;
        $this->notFound = $notFound;
        $this->methodNotAllowed = $methodNotAllowed;
    }

    /**
     * Get routes
     *
     * @return Routes
     */
    public function getRoutes()
    {
        return $this->routes;
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

        if ($this->getRoutes()->hasRoute($request)) {
            return $next ? $next($request, $response) : $response;    
        }

        $status = $this->methodNotAllowed && $this->getRoutes()->hasRoute($request, false) ? 
            $this->methodNotAllowed : $this->notFound;

        return is_numeric($status) ? $this->simpleResponse($response, $status) : call_user_func($status, $request, $response);
    }

    /**
     * Simple response
     *
     * @param ResponseInterface $response
     * @param int $code 
     * @return ResponseInterface
     */
    protected function simpleResponse(ResponseInterface $response, $code)
    {
        $message = 'Not Found';

        $body = $response->getBody();        
        $body->rewind();
        $body->write($message);

        return $response->withStatus($code, $message)->withBody($body);
    }
}
