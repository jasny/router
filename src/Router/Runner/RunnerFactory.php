<?php

namespace Jasny\Router\Runner;

use Jasny\Router\Route;

/**
 * Factory of Runner instances
 */
class RunnerFactory
{        
    /**
     * Create Runner instance
     * 
     * @param Route $route
     * @return Runner
     */
    public function __invoke(Route $route)
    {
        if (isset($route->controller)) {
            $class = Controller::class;
        } elseif (isset($route->fn)) {
            $class = Callback::class;
        } elseif (isset($route->file)) {
            $class = PhpScript::class;
        } else {
            throw new \InvalidArgumentException("Route has neither 'controller', 'fn' or 'file' defined");
        }

        return new $class();
    }
}

