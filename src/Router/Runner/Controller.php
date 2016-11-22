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
     * Get class name from controller name
     * 
     * @param string $name
     * @return string
     */
    protected function getClass($name)
    {
        return \Jasny\studlycase($name) . 'Controller';
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
            throw new \RuntimeException("Can not route to controller '$class': class not exists");
        }
        
        if (!method_exists($class, '__invoke')) {
            throw new \RuntimeException("Can not route to controller '$class': class does not have '__invoke' method");   
        }
        
        $controller = $this->instantiate($class);
        
        return $controller($request, $response);
    }
}
