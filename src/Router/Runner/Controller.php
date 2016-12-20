<?php

namespace Jasny\Router\Runner;

use Jasny\Router\Runner;
use Jasny\Router\ControllerFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Run a route using a controller
 */
class Controller
{
    use Runner\Implementation;

    /**
     * Factory for dependecy injection
     * 
     * @var callable 
     */
    protected $factory;
    
    
    /**
     * Create a clone that uses the provided factory
     * 
     * @param callable $factory
     */
    public function withFactory($factory)
    {
        if (!is_callable($factory)) {
            throw new \InvalidArgumentException("Factory isn't callable");
        }
        
        $runner = clone $this;
        $runner->factory = $factory;
        
        return $runner;
    }
    
    /**
     * Get the controller factory
     * 
     * @param boolean $forceCallable
     * @return callable|ContainerInterface
     */
    public function getFactory()
    {
        if (!isset($this->factory)) {
            $this->factory = new ControllerFactory();
        }
        
        return $this->factory;
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
        $name = !empty($route->controller) ? $route->controller : 'default';
        
        try {
            $controller = call_user_func($this->getFactory(), $name);
        } catch (\Exception $ex) {
            trigger_error($ex->getMessage(), E_USER_NOTICE);
            return $this->notFound($request, $response);
        }

        if (!method_exists($controller, '__invoke')) {
            $class = get_class($controller);
            trigger_error("Can't route to controller '$class': class does not have '__invoke' method", E_USER_NOTICE);
            return $this->notFound($request, $response);
        }
        
        return $controller($request, $response);
    }
}
