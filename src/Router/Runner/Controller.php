<?php

namespace Jasny\Router\Runner;

use Jasny\Router\Runner;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Run a route using a controller
 */
class Controller extends Runner
{
    /**
     * Return with a 404 not found response
     * 
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function notFound(ServerRequestInterface $request, ResponseInterface $response)
    {
        $finalResponse = $response
            ->withProtocolVersion($request->getProtocolVersion())
            ->withStatus(404)
            ->withHeader('Content-Type', 'text/plain');
        
        $finalResponse->getBody()->write("Not found");
        
        return $finalResponse;
    }
    
    /**
     * Get class name from controller name
     * 
     * @param string|array $name
     * @return string
     */
    protected function getClass($name)
    {
        return join('\\', array_map('Jasny\studlycase', (array)$name)) . 'Controller';
    }
    
    /**
     * Instantiate a controller object
     * @codeCoverageIgnore
     * 
     * @param string $class
     * @return callable|object
     */
    protected function instantiate($class)
    {
        return new $class();
    }
    
    /**
     * Route to a controller
     * 
     * @param ServerRequestInterface  $request
     * @param ResponseInterface $response
     * @return ResponseInterface|mixed
     */
    public function run(ServerRequestInterface $request, ResponseInterface $response)
    {
        $route = $request->getAttribute('route');        
        $name = !empty($route->controller) ? $route->controller : null;
        
        $class = $this->getClass($name);
        
        if (!class_exists($class)) {
            trigger_error("Can't route to controller '$class': class not exists", E_USER_NOTICE);
            return $this->notFound($request, $response);
        }
        
        if (!method_exists($class, '__invoke')) {
            trigger_error("Can't route to controller '$class': class does not have '__invoke' method", E_USER_NOTICE);   
            return $this->notFound($request, $response);
        }
        
        $controller = $this->instantiate($class);
        
        return $controller($request, $response);
    }
}
