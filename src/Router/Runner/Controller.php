<?php

namespace Jasny\Router\Runner;

use Jasny\Router\Runner;
use Psr\Http\Message\RequestInterface;
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
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @return ResponseInterface|mixed
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {
        $class = !empty($this->route->controller) ? $this->route->controller : null;

        if (!class_exists($class)) {
            throw new \RuntimeException("Can not route to controller '$class': class not exists");
        }

        if (!method_exists($class, '__invoke')) {
            throw new \RuntimeException("Can not route to controller '$class': class does not have '__invoke' method");   
        }

        $controller = new $class($this->route);

        return $controller($request, $response);
    }
}
