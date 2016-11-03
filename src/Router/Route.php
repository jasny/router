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
     * @param array|stdClass $values
     */
    public function __construct($values)
    {
        if ($values instanceof \stdClass) {
            $values = get_object_vars($values);
        }
        
        if (!is_array($values)) {
            $type = (is_object($values) ? get_class($values) . ' ' : '') . gettype($values);
            throw new \InvalidArgumentException("Route values should be an array, not a $type");
        }
        
        foreach ($values as $key => $value) {
            $this->$key = $value;
        }
    }
}
