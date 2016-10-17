<?php

namespace Jasny\Router\Runner;

use Jasny\Router\Runner;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Description of Controller
 *
 * @author arnold
 */
class Controller extends Runner
{
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
        $class = !empty($route->controller) ? $route->controller : null;

        if (!class_exists($class)) {
            throw new \RuntimeException("Can not route to controller '$class': class not exists");
        }

        if (!method_exists($class, '__invoke')) {
            throw new \RuntimeException("Can not route to controller '$class': class does not have '__invoke' method");   
        }

        $controller = new $class($route);

        return $controller($request, $response);
    }
}
