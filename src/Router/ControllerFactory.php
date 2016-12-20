<?php

namespace Jasny\Router;

use Jasny\Router\FactoryException;

/**
 * Factory for controller objects
 */
class ControllerFactory
{
    /**
     * @var callable
     */
    protected $chain;
    
    
    /**
     * Class constructor
     * 
     * @param callable $chain  Instantiate method
     */
    public function __construct($chain = null)
    {
        if (isset($chain) && !is_callable($chain)) {
            throw new \InvalidArgumentException("Chain should be callable");
        }
        
        $this->chain = $chain;
    }
    
    
    /**
     * Assert that a class exists and will provide a callable object
     * 
     * @throws FactoryException
     */
    protected function assertClass($class)
    {
        if (!preg_match('/^([a-zA-Z_]\w*\\\\)*[a-zA-Z_]\w*$/', $class)) {
            throw new \UnexpectedValueException("Can't route to controller '$class': invalid classname");
        }
        
        if (!class_exists($class)) {
            throw new \UnexpectedValueException("Can't route to controller '$class': class not exists");
        }

        $refl = new \ReflectionClass($class);
        $realClass = $refl->getName();
        
        if ($realClass !== $class) {
            throw new \UnexpectedValueException("Can't route to controller '$class': case mismatch with '$realClass'");
        }
    }

    /**
     * Turn kabab-case into StudlyCase.
     * 
     * @internal Jasny\studlycase isn't used because it's to tolerent, which might lead to security issues.
     * 
     * @param string $string
     * @return string
     */
    protected function studlyCase($string)
    {
        return preg_replace_callback('/(?:^|(\w)-)(\w)/', function($match) {
            return $match[1] . strtoupper($match[2]);
        }, strtolower(addcslashes($string, '\\')));
    }
    
    /**
     * Get class name from controller name
     * 
     * @param string|array $name
     * @return string
     */
    protected function getClass($name)
    {
        return join('\\', array_map([$this, 'studlyCase'], (array)$name)) . 'Controller';
    }
    
    /**
     * Instantiate a controller object
     * 
     * @param string $class
     * @return callable|object
     */
    protected function instantiate($class)
    {
        return new $class();
    }
    
    
    /**
     * Create a controller
     * 
     * @param string $name
     * @return object|callable
     */
    public function __invoke($name)
    {
        $class = $this->getClass($name);
        $this->assertClass($class);
        
        return $this->chain ? call_user_func($this->chain, $class) : $this->instantiate($class);
    }
}
