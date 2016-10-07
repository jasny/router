<?php

namespace Jasny\Router;

/**
 * A route
 */
class Route extends \stdClass
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
        if ($values instanceof \stdClass) {
            $values = get_object_vars($values);
        }
        
        return new static($values);
    }
}
