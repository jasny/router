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
     * Create a controller object
     * 
     * @param string $name
     * @return 
     */
    protected function instantiateController($name)
    {
        $class = \Jasny\studlycase($name);
        
        if (!class_exists($class)) {
            throw new \RuntimeException("Can not route to controller '$class': class not exists");
        }

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

        $controller = $this->instantiateController($name);
        
        if (!method_exists($controller, '__invoke')) {
            throw new \RuntimeException("Can not route to controller '$class': class does not have '__invoke' method");   
        }
        
        return $controller($request, $response);
    }
}
