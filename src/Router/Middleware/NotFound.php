<?php

namespace Jasny\Router\Middleware;

use Jasny\Router\RoutesInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Set response to 'not found' or 'method not allowed' if route is non exist 
 */
class NotFound
{    
    /**
     * Routes
     * @var RoutesInterface
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
     * @param RoutesInterface $routes
     * @param callback|int $notFound 
     * @param callback|int $methodNotAllowed 
     */
    public function __construct(RoutesInterface $routes, $notFound = 404, $methodNotAllowed = null)
    {
        if (is_string($notFound) && ctype_digit($notFound)) {
            $notFound = (int)$notFound;
        }
        if (!(is_int($notFound) && $notFound >= 100 && $notFound <= 999) && !is_callable($notFound)) {
            throw new \InvalidArgumentException("'notFound' should be valid HTTP status code or a callback");
        }

        if (is_string($methodNotAllowed) && ctype_digit($methodNotAllowed)) {
            $methodNotAllowed = (int)$methodNotAllowed;
        }
        if (
            isset($methodNotAllowed) &&
            !(is_int($methodNotAllowed) && $methodNotAllowed >= 100 && $methodNotAllowed <= 999) &&
            !is_callable($methodNotAllowed)
        ) {
            throw new \InvalidArgumentException("'methodNotAllowed' should be valid HTTP status code or a callback");   
        }

        $this->routes = $routes;
        $this->notFound = $notFound;
        $this->methodNotAllowed = $methodNotAllowed;
    }

    /**
     * Get routes
     *
     * @return RoutesInterface
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
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        if (!is_callable($next)) {
            throw new \InvalidArgumentException("'next' should be a callback");            
        }

        if (!$this->getRoutes()->hasRoute($request)) {
            $status = $this->methodNotAllowed && $this->getRoutes()->hasRoute($request, false) ? 
                $this->methodNotAllowed : $this->notFound;

            return is_numeric($status) ? $this->simpleResponse($response, $status) : $status($request, $response);
        }
        
        return $next($request, $response);
    }

    /**
     * Simple response
     *
     * @param ResponseInterface $response
     * @param int               $code
     * @return ResponseInterface
     */
    protected function simpleResponse(ResponseInterface $response, $code)
    {
        $notFound = $response->withStatus($code);
        $notFound->getBody()->write('Not found');

        return $notFound;
    }
}
