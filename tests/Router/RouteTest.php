<?php

namespace Jasny\Router;

use Jasny\Router\Route;

/**
 * @covers Jasny\Router\Route
 */
class RouteTest extends \PHPUnit_Framework_TestCase
{
    public function provider()
    {
        return [
            [['foo' => '$1', 'color' => 'red', 'number' => 42]],
            [(object)['foo' => '$1', 'color' => 'red', 'number' => 42]]
        ];
    }
    
    /**
     * @dataProvider provider
     * 
     * @param array|stdClass $values
     */
    public function testConstructionWithObject($values)
    {
        $route = new Route($values);
        
        $this->assertAttributeSame('$1', 'foo', $route);
        $this->assertAttributeSame('red', 'color', $route);
        $this->assertAttributeSame(42, 'number', $route);
    }
    
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Route values should be an array, not a string
     */
    public function testConstructionInvalidArgument()
    {
        new Route('foo');
    }
}
