<?php

namespace Jasny;

/**
 * A route
 */
class Route extends stdClass
{
    /**
     * Class constructor
     * 
     * @param array $values
     */
    public function __construct(array $values)
    {
        foreach ($values as $key => $value) {
            $this->$key = $value;
        }
    }
    
    /**
     * Factory method
     * 
     * @param array|\stdClass $values
     * @return Route
     */
    public static function create($values)
    {
        if ($values instanceof stdClass) {
            $values = get_object_vars($values);
        }
        
        if (isset($values['controller'])) {
            $callback = Jasny\array_only($values, ['controller', 'action']) + ['action' => 'default'];
            $route = new Route\Callback($callback, $values);
        } elseif (isset($values['fn'])) {
            $route = new Route\Callback($values['fn'], $values);
        } elseif (isset($values['file'])) {
            $route = new Route\PhpScript($values['file'], $values);
        } else {
            throw new \Exception("Route has neither 'controller', 'fn' or 'file' defined");
        }
        
        return $route;
    }
}
