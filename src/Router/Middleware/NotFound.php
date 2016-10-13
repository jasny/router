<?php

namespace Jasny\Router\Middleware;

use Jasny\Router\Runner;
use Jasny\Router\Routes\Glob;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Set response to 'not found' or 'method not allowed' if route is non exist 
 */
class NotFound
{    
    /**
     * Routes
     * @var array
     */
    protected $routes = [];

    /**
     * Action for 'not found' case
     * @var callback|int
     **/
    protected $notFound = null;

    /**
     * Action for 'method not allowed' case
     * @var string
     **/
    protected $methodNotAllowed = null;

    /**
     * Class constructor
     * 
     * @param array $routes
     * @param callback|int $notFound 
     * @param callback|int $methodNotAllowed 
     */
    public function __construct(array $routes, $notFound = 404, $methodNotAllowed = null)
    {
        if (!in_array($notFound, [404, '404'], true) && !is_callable($notFound)) {
            throw new \InvalidArgumentException("'Not found' parameter should be '404' or a callback");
        }

        if ($methodNotAllowed && !in_array($methodNotAllowed, [405, '405'], true) && !is_callable($methodNotAllowed)) {
            throw new \InvalidArgumentException("'Method not allowed' parameter should be '405' or a callback");   
        }

        $this->routes = $routes;
        $this->notFound = $notFound;
        $this->methodNotAllowed = $methodNotAllowed;
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

        $glob = new Glob($this->routes);          

        if ($this->methodNotAllowed) {
            $notAllowed = !$glob->hasRoute($request) && $glob->hasRoute($request, false);

            if ($notAllowed) {
                return is_numeric($this->methodNotAllowed) ? 
                    $this->simpleResponse($response, $this->methodNotAllowed, 'Method Not Allowed') :
                    call_user_func($this->methodNotAllowed, $request, $response);
            }
        } 

        if (!$glob->hasRoute($request)) {
            return is_numeric($this->notFound) ? 
                $this->simpleResponse($response, $this->notFound, 'Not Found') :
                call_user_func($this->notFound, $request, $response);
        }

        return $next ? $next($request, $response) : $response;
    }

    /**
     * Simple response
     *
     * @param ResponseInterface $response
     * @param int $code 
     * @param string $message 
     * @return 
     */
    protected function simpleResponse(ResponseInterface $response, $code, $message)
    {
        $body = $response->getBody();        
        $body->rewind();
        $body->write($message);

        return $response->withStatus($code, $message)->withBody($body);
    }
}
