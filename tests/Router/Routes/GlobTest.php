<?php

namespace Jasny\Router\Routes;

use Jasny\Router\Routes\Glob;
use Jasny\Router\Route;
use Psr\Http\Message\ServerRequestInterface;

use ArrayObject;
use BadMethodCallException;
use InvalidArgumentException;

/**
 * @covers Jasny\Router\Routes\Glob
 * @covers Jasny\Router\UrlParsing
 */
class GlobTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test creating Glob object
     */
    public function testConstructor()
    {   
        $glob = new Glob();
        $this->assertInstanceOf('ArrayObject', $glob, "Should be an instance of 'ArrayObject'");
        $this->assertEquals(0, $glob->count(), "Default count is not empty");
        $this->assertEquals(0, $glob->getFlags(), "Default flags are not empty");
        $this->assertEquals('ArrayIterator', $glob->getIteratorClass(), "Default iterator class is not correct");

        // Actual check for available routes
        $count = 0;
        foreach ($glob as $value) {
            $count++;
            break;
        }

        $this->assertEquals(0, $count);
    }
    
    public function testConstructorWithArguments()
    {
        $values = [
            '/foo/bar' => ['controller' => 'value1'],
            '/foo/*' => ['fn' => 'value3'],
            '/foo/*/bar' => ['file' => 'value5'],
        ];
        
        $glob = new Glob($values, ArrayObject::ARRAY_AS_PROPS);

        $this->assertCount(3, $glob, "Routes count do not match");
        $this->assertEquals(ArrayObject::ARRAY_AS_PROPS, $glob->getFlags(), "Flags are not correct");

        foreach ($glob as $pattern => $route) {
            $this->assertInstanceof(Route::class, $route);
            $this->assertArrayHasKey($pattern, $values);
            $this->assertArraysEqual($values[$pattern], (array)$route);
        }
        
        return $glob;
    }
    
    /**
     * @depends testConstructorWithArguments
     * 
     * @param Glob $original
     */
    public function testConstructorTraversable(Glob $original)
    {
        $glob = new Glob($original);
        
        $this->assertCount(3, $glob, "Routes count do not match");
        $this->assertEquals($original->getArrayCopy(), $glob->getArrayCopy());
    }

    /**
     * Provide data for testExchangeArray() test method
     */
    public function exchangeArrayProvider()
    {
        return [
            [
                [], 
                ['/foo/bar' => ['fn' => 'value1'], '/foo/*' => ['fn' => 'value3']]
            ],
            [
                ['/foo/bar' => ['fn' => 'value1'], '/foo/*' => ['fn' => 'value3']],
                []  
            ],
            [
                ['/foo/bar' => ['fn' => 'value1'], '/foo/*' => ['fn' => 'value3']],
                ['/bar' => ['fn' => 'value1']],
            ]
        ];
    }

    /**
     * Test ArrayObject::exchangeArray method
     * 
     * @dataProvider exchangeArrayProvider
     */
    public function testExchangeArray($set, $reset)
    {
        $glob = new Glob($set);
        $old = $glob->exchangeArray($reset); 

        $this->assertEquals(count($set), count($old), "Old routes count do not match");
        $this->assertEquals(count($reset), $glob->count(), "Routes count do not match");

        foreach ($reset as $pattern => $options) {
            $this->assertTrue($glob->offsetExists($pattern), "Key is missing");    
        }
        foreach ($set as $pattern => $options) {
            $this->assertTrue(!empty($old[$pattern]), "Old key is missing");                
            $this->assertFalse($glob->offsetExists($pattern), "Key exists, but should not");
        }
    }


    /**
     * Provide data for testOffsetSet()
     */
    public function offsetSetProvider()
    {
        return [
            ['/foo/*', ['controller' => 'bar']],
            ['/foo/*', ['fn' => 'bar']],
            ['/foo/*', ['file' => 'bar']],
            ['/foo/*', $this->getMockBuilder(Route::class)->setConstructorArgs([['controller' => 'bar']])->getMock()]
        ];
    }
    
    /**
     * Test ArrayObject::offsetSet method
     * @dataProvider offsetSetProvider
     * 
     * @param string $pattern 
     * @param array $options 
     */
    public function testOffsetSet($pattern, $options)
    {
        $glob = new Glob();
        $glob[$pattern] = $options;

        $this->assertCount(1, $glob);
        $this->assertTrue(isset($glob[$pattern]));
        
        $route = $glob[$pattern];
        $this->assertInstanceOf(Route::class, $route);
        
        if ($options instanceof Route) {
            $this->assertSame($options, $route);
        } else {
            $this->assertEquals([], array_diff($options, (array)$route));
        }
    }
    
    /**
     * @expectedException BadMethodCallException
     */
    public function testOffsetSetInvalidPattern()
    {
        $glob = new Glob();
        $glob[''] = ['controller' => 'bar'];
    }
    
    /**
     * @expectedException InvalidArgumentException
     */
    public function testOffsetSetInvalidRoute()
    {
        $glob = new Glob();
        $glob['/foo'] = 'bar';
    }

    /**
     * Test ArrayObject::append method
     *
     * @expectedException BadMethodCallException
     */
    public function testAppend()
    {
        $glob = new Glob();
        $glob->append(['controller' => 'bar']);
    }

    /**
     * Provide data for testFnMatch()
     */
    public function fnMatchProvider()
    {
        return [
            ['/*', '/foo', true],
            ['/foo/*', '/foo/bar', true],
            ['/foo/*/bar/*/teta', '/foo/zet/bar/omega/teta', true],
            ['/**', '/', true],
            ['/**', '/foo/bar/zet', true],
            ['/foo/**', '/foo/bar/zet', true],
            ['/#', '/12345', true],
            ['/foo/#/bar', '/foo/12345/bar', true],
            ['/foo/bar#', '/foo/bar1', true],
            ['/foo?/bar', '/foo1/bar', true],
            ['/?', '/a', true],
            ['/foo[a-d]/foo', '/food/foo', true],
            ['/foo[sad]/bar', '/food/bar', true],
            ['/foo/bar.{png,gif}', '/foo/bar.png', true],
            ['/foo/bar.{png,gif}', '/foo/bar.gif', true],

            ['', '/foo', false],
            ['?', '/', false],
            ['/*', '/foo/bar', false],
            ['/foo/*', '/foo/bar/zet', false],
            ['/foo/*/bar', '/foo/zet/teta/bar', false],
            ['/#', '/12345foo', false],
            ['/#', '/12345/foo', false],
            ['/foo/#/bar', '/foo/foo12345/bar', false],
            ['/foo?/bar', '/foo12/bar', false],
            ['/?', '/ab', false],
            ['/foo[a-d]/foo', '/fooe/foo', false],
            ['/foo[sad]/bar', '/foosad/bar', false],
            ['/foo/bar.{png,gif}', '/foo/bar.pn', false],
            ['/foo/bar.{png,gif}', '/foo/bar.if', false],
            ['/foo/bar.{png,gif}', '/foo/bar.', false]
        ];
    }

    /**
     * Test matching of url pattern to given uri
     * @dataProvider fnMatchProvider
     * 
     * @param string $pattern 
     * @param string $uri 
     * @param boolean $positive 
     */
    public function testFnMatch($pattern, $uri, $positive)
    {   
        $glob = new Glob();

        $this->assertEquals($positive, $glob->fnmatch($pattern, $uri),
            "Pattern and uri should " . ($positive ? "" : "not") . " match");
    }

    /**
     * Testing getting route and it's existense
     * @dataProvider getHasRouteProvider
     * 
     * @param string  $uri       Uri of ServerRequest
     * @param string  $method    Query method name
     * @param boolean $positive  If the test should be positive or negative
     */
    public function testGetHasRoute($uri, $method, $positive)
    {
        $values = [
            '/' => ['controller' => 'value0'],
            '/foo/bar' => ['controller' => 'value1'],
            '/foo +GET' => ['controller' => 'value2'],
            '/bar/foo/zet -POST' => ['controller' => 'value3']
        ];

        $glob = new Glob($values);
        $request = $this->getServerRequest($uri, $method);
        $route = $glob->getRoute($request);
        $exist = $glob->hasRoute($request);

        if (!$positive) {
            return $this->assertEmpty($route, "Route obtained, but should not") && 
                $this->assertEmpty($exist, "Route exists, but should not");
        }

        $match = '';
        foreach ($values as $pattern => $value) {
            if (!preg_match('|^' . preg_quote($uri) . '[^/]*$|', $pattern)) continue;

            $match = $pattern;
            break;
        }

        $this->assertTrue($exist, "Route not exists");
        $this->assertTrue((bool)$match, "Found no match of uri with patterns");
        $this->assertEquals($values[$match]['controller'], $route->controller, "False route obtained");
    }

    /**
     * Provide data for creating ServerRequestInterface objects
     */
    public function getHasRouteProvider()
    {
        return [
            ['/', 'GET', true],
            ['/foo/bar', 'GET', true],
            ['/foo', 'GET', true],
            ['/foo', 'POST', false],
            ['/bar/foo/zet', 'GET', true],
            ['/bar/foo/zet', 'POST', false]
        ];
    }

    /**
     * Test checking if route exists regardless of request method
     *
     * @dataProvider getHasRouteNoMethodProvider
     */
    public function testHasRouteNoMethod($uri, $method)
    {
        $values = [
            '/' => ['controller' => 'value0'],
            '/foo/bar' => ['controller' => 'value1'],
            '/foo +GET' => ['controller' => 'value2'],
            '/bar/foo/zet -POST' => ['controller' => 'value3']
        ];

        $glob = new Glob($values);
        $request = $this->getServerRequest($uri, $method);

        $this->assertTrue($glob->hasRoute($request, false), "Route not exists");
    }

    /**
     * Provide data for creating ServerRequestInterface objects
     */
    public function getHasRouteNoMethodProvider()
    {
        return [
            ['/', 'GET'],
            ['/foo/bar', 'GET'],
            ['/foo', 'GET'],
            ['/foo', 'POST'],
            ['/bar/foo/zet', 'GET'],
            ['/bar/foo/zet', 'POST']
        ];
    }

    /**
     * Get ServerRequestInterface object
     *
     * @param string $uri
     * @param string $method  Http query method
     * @return ServerRequestInterface
     */
    public function getServerRequest($uri, $method = 'GET', $globals = [], $header = '')
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getMethod')->willReturn($method);
        $request->method('getQueryParams')->willReturn(isset($globals['get']) ? $globals['get'] : []);
        $request->method('getParsedBody')->willReturn(isset($globals['post']) ? $globals['post'] : []);
        $request->method('getCookieParams')->willReturn(isset($globals['cookie']) ? $globals['cookie'] : []);
        $request->method('getHeaderLine')->willReturn($header);

        return $request;
    }

    /**
     * Assert that two 1-dimensional arrays are equal.
     * Use if array elements are scalar values, or objects with __toString() method
     *
     * @param array $array1
     * @param array $array2 
     */
    public function assertArraysEqual(array $array1, array $array2)
    {
        $this->assertEmpty(array_diff($array2, $array1), 'Missing items');
        $this->assertEmpty(array_diff($array1, $array2), 'Additional items');
    }
}
