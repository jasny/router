<?php

namespace Jasny\Router\Routes;

use Jasny\Router\Routes\Glob;
use Jasny\Router\Route;
use Psr\Http\Message\ServerRequestInterface;

use ArrayObject;
use BadMethodCallException;
use InvalidArgumentException;

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
     * Test binding simple string when getting route
     */
    public function testBindVarString()
    {
        $uri = '/foo/bar';
        $values = [$uri => ['controller' => 'value1', 'check' => 'value1']];

        $glob = new Glob($values);
        $request = $this->getServerRequest($uri);        
        $route = $glob->getRoute($request);

        $this->assertEquals($values[$uri]['check'], $route->check);
    }

    /**
     * Provide uri's and corresponding patterns for testBindVarSingleUrlPart()
     */
    public function bindVarSingleUrlPartProvider()
    {
        return [
            ['/*', '/test', ['check' => '$1'], 'test'],
            ['/', '/', ['check' => '$1|test'], 'test'],
            ['/foo/*/bar', '/foo/test/bar', ['check' => '$2'], 'test'],
            ['/foo/bar/*', '/foo/bar/test', ['check' => '$3'], 'test'],
            ['/foo/bar/*/zet/*', '/foo/bar/test1/zet/test2', ['check' => '$3', 'checkB' => '$5'], 'test1', 'test2'],
            ['/foo/bar/*/zet/*', '/foo/bar/test1/zet/test2', ['check' => '~$3~/~$5~'], 'test1/test2'],
            ['/', '/', ['check' => '$foo'], null]
        ];
    }

    /**
     * Test binding single url part to route option
     * @dataProvider bindVarSingleUrlPartProvider
     * 
     * @param string $pattern
     * @param string $uri 
     * @param array  $options   Route options
     * @param string $check     Expected value for `check`
     * @param string $checkB    Expected value for `checkB`
     */
    public function testBindVarSingleUrlPart($pattern, $uri, $options, $check, $checkB = null)
    {
        $values = [$pattern => $options];

        $glob = new Glob($values);
        $request = $this->getServerRequest($uri);        
        $route = $glob->getRoute($request);

        $this->assertNotNull($route, "Route not found");
        $this->assertInstanceOf(Route::class, $route);

        $this->assertEquals($check, $route->check);
        
        if (isset($checkB)) {
            $this->assertEquals($checkB, $route->checkB);
        }
    }
    
    public function testBindVarWithObject()
    {
        $object = new \Exception(); // Could be anything, just not stdClass
        $glob = new Glob(['/' => ['object' => $object]]);
        
        $request = $this->getServerRequest('/');        
        $route = $glob->getRoute($request);

        $this->assertNotNull($route, "Route not found");
        $this->assertInstanceOf(Route::class, $route);
        
        $this->assertSame($object, $route->object);
    }

    public function bindVarWithSubProvider()
    {
        return [
            [['group' => ['check' => '$1']], 'array'],
            [['group' => (object)['check' => '$1']], 'object'],
            [['group' => ['sub' => (object)['check' => '$1']]], 'array', 'object'],
            [['group' => (object)['sub' => ['check' => '$1']]], 'object', 'array']
        ];
    }
    
    /**
     * @dataProvider bindVarWithSubProvider
     * 
     * @param array  $options
     * @param string $type
     * @param string $subtype
     */
    public function testBindVarWithSub(array $options, $type, $subtype = null)
    {
        $glob = new Glob(['/*' => $options]);
        
        $request = $this->getServerRequest('/test');        
        $route = $glob->getRoute($request);

        $this->assertNotNull($route, "Route not found");
        $this->assertInstanceOf(Route::class, $route);
        
        $this->assertInternalType($type, $route->group);
        
        $group = (array)$route->group;
        
        if (isset($subtype)) {
            $this->assertArrayHasKey('sub', $group);
            $this->assertInternalType($subtype, $group['sub']);
            
            $group = (array)$group['sub'];
        }
        
        $this->assertEquals($group, ['check' => 'test']);
    }
    
    
    /**
     * Provide uri's and corresponding patterns for testBindVarMultipleUrlParts()
     */
    public function bindVarMultipleUrlPartsProvider()
    {
        return [
            ['/foo', ['check' => '$1...'], false, InvalidArgumentException::class],
            ['/', ['check' => ['$1...']], false],
            ['/foo', ['check' => ['$1...']], true],
            ['/foo/bar', ['check' => ['$1...'], 'checkB' => ['$2...']],
                InvalidArgumentException::class]
        ];
    }

    /**
     * Test binding multyple url parts to route option
     * @dataProvider bindVarMultipleUrlPartsProvider
     * 
     * @param string  $uri 
     * @param array   $options     Route options
     * @param boolean $positive
     * @param string  $exception
     */
    public function testBindVarMultipleUrlParts($uri, $options, $positive, $exception = false)
    {
        if ($exception) {
            $this->expectException($exception);
        }
        
        $glob = new Glob([$uri => $options]);
        $request = $this->getServerRequest($uri);        
        $route = $glob->getRoute($request);

        if ($exception) return;

        $this->assertNotNull($route, "Route not found");
        $this->assertInstanceOf(Route::class, $route);

        $values = explode('/', trim($uri, '/'));
        
        $positive ?
            $this->assertArraysEqual($values, $route->check, "Multyple url parts are not picked correctly") :
            $this->assertEmpty($route->check, "Multyple parts element should be empty");

        if (!empty($options->checkB)) {
            array_shift($values);
            $this->assertArraysEqual($values, $route->checkB, "Secondary multyple url parts are not picked correctly");
        }
    }

    /**
     * Provide uri's and corresponding patterns for testBindVarMultipleUrlParts()
     */
    public function bindVarSuperGlobalProvider()
    {
        return [
            ['/foo', ['check' => '$_GET[check]'], 'get'],
            ['/foo', ['check' => '$_POST[check]'], 'post'],
            ['/foo', ['check' => '$_COOKIE[check]'], 'cookie']
        ];
    }

    /**
     * Test binding element of superglobal array to route option
     * @dataProvider bindVarSuperGlobalProvider
     * 
     * @param string $uri 
     * @param array $options 
     * @param string $type    ('get', 'post', 'cookie')
     */
    public function testBindVarSuperGlobal($uri, $options, $type)
    {
        $test = ['check' => 'test'];
        $glob = new Glob([$uri => $options]);
        $request = $this->getServerRequest($uri, 'GET', [$type => $test]);
        $route = $glob->getRoute($request);

        $this->assertEquals($test['check'], $route->check, "Did not obtaine value for superglobal '$type'");
    }

    /**
     * Test binding element of superglobal array to route option
     */
    public function testBindVarRequestHeader()
    {   
        $uri = '/foo/bar';
        $test = 'test_header_value';
        
        $glob = new Glob([$uri => ['check' => '$HTTP_REFERER']]);
        $request = $this->getServerRequest($uri, 'GET', [], $test);
        
        $route = $glob->getRoute($request);
        $this->assertNotNull($route, "Route not found");

        $this->assertEquals($test, $route->check);
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
