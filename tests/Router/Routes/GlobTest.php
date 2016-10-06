<?php

use Jasny\Router\Routes\Glob;
use Psr\Http\Message\ServerRequestInterface;

class GlobTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test creating Glob object
     */
    public function testConstructor()
    {   
        #Test empty constructor
        $glob = new Glob();
        $this->assertInstanceOf('ArrayObject', $glob, "Should be an instance of 'ArrayObject'");
        $this->assertEquals(0, $glob->count(), "Default count is not empty");
        $this->assertEquals(0, $glob->getFlags(), "Default flags are not empty");
        $this->assertEquals('ArrayIterator', $glob->getIteratorClass(), "Default iterator class is not correct");

        #Actual check for public values
        $count = 0;
        foreach ($glob as $value) {
            $count++;
            break;
        }

        $this->assertEquals(0, $count);        

        #Test with params
        $values = [
            '/foo/bar' => ['controller' => 'value1'],
            '/foo/*' => ['fn' => 'value3'],
            '/foo/*/bar' => ['file' => 'value5'],
        ];
        $glob = new Glob($values, ArrayObject::ARRAY_AS_PROPS, 'AppendIterator');

        $this->assertEquals(count($values), $glob->count(), "Routes count is not match");
        $this->assertEquals(ArrayObject::ARRAY_AS_PROPS, $glob->getFlags(), "Flags are not correct");
        $this->assertEquals('AppendIterator', $glob->getIteratorClass(), "Iterator class is not correct");

        foreach ($values as $pattern => $options) {
            $this->assertTrue($glob->offsetExists($pattern), "Key '$pattern' is missing");
        }
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

        $this->assertEquals(count($set), count($old), "Old routes count is not match");
        $this->assertEquals(count($reset), $glob->count(), "Routes count is not match");

        foreach ($reset as $pattern => $options) {
            $this->assertTrue($glob->offsetExists($pattern), "Key is missing");    
        }
        foreach ($set as $pattern => $options) {
            $this->assertTrue(!empty($old[$pattern]), "Old key is missing");                
            $this->assertFalse($glob->offsetExists($pattern), "Key exists, but should not");
        }
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
     * Test ArrayObject::offsetSet method
     * 
     * @dataProvider offsetSetProvider
     * @param string $pattern 
     * @param array $options 
     * @param string $exception 
     */
    public function testOffsetSet($pattern, $options, $exception)
    {
        if ($exception) $this->expectException($exception);

        $glob = new Glob();
        $glob->offsetSet($pattern, $options);

        if ($exception) return;

        $this->assertEquals(1, $glob->count(), "Routes count is not match");
        $this->assertTrue($glob->offsetExists($pattern), "Key is missing");
        
        #Verify that value was set correctly
        $value = (array)$glob->offsetGet($pattern);
        $this->assertEmpty(array_diff($options, $value), "Route was not set correct");
    }

    /**
     * Provide data for testOffsetSet()
     */
    public function offsetSetProvider()
    {
        return [
            ['/foo/*', ['controller' => 'bar'], ''],
            ['/foo/*', ['fn' => 'bar'], ''],
            ['/foo/*', ['file' => 'bar'], ''],
            ['', ['controller' => 'bar'], BadMethodCallException::class],
            ['/bar', ['foo' => 'bar'], InvalidArgumentException::class],
            ['', '', BadMethodCallException::class]
        ];
    }

    /**
     * Test ArrayObject::append method
     */
    public function testAppend()
    {
        $glob = new Glob();

        $this->expectException(BadMethodCallException::class);
        $glob->append(['controller' => 'bar']);
    }

    /**
     * Test matching of url pattern to given uri
     *
     * @dataProvider fnMatchProvider
     * @param string $pattern 
     * @param string $uri 
     * @param boolean $positive 
     */
    public function testFnMatch($pattern, $uri, $positive)
    {   
        $glob = new Glob();

        $this->assertEquals($positive, $glob->fnmatch($pattern, $uri), "Pattern and uri should " . ($positive ? "" : "not") . " match");
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
     * Testing getting route and it's existense
     * 
     * @dataProvider getHasRouteProvider
     * @param string $uri         Uri of ServerRequest
     * @param string $method      Query method name
     * @param boolean $positive   If the test should be positive or negative
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
        $this->assertEquals($values[$match]['controller'], $route['controller'], "False route obtained");
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
     * Test binding simple string when getting route
     */
    public function testBindVarString()
    {
        $uri = '/foo/bar';
        $values = [$uri => ['controller' => 'value1', 'check' => 'value1']];

        $glob = new Glob($values);
        $request = $this->getServerRequest($uri);        
        $route = $glob->getRoute($request);

        $this->assertEquals($route['check'], $values[$uri]['check'], "Option value is not correct");
    }

    /**
     * Test binding single url part to route option
     * @dataProvider bindVarSingleUrlPartProvider
     * @param string $patter 
     * @param string $uri 
     * @param array $options   Route options
     */
    public function testBindVarSingleUrlPart($pattern, $uri, $options)
    {
        $values = [$pattern => $options];

        $glob = new Glob($values);
        $request = $this->getServerRequest($uri);        
        $route = $glob->getRoute($request);

        $this->assertTrue((bool)$route, "Route not found");

        if (!empty($options['check'])) {
            $this->assertEquals('test', $route['check'], "Single pocket did not match");            
        } elseif(empty($options['check2'])) {
            $this->assertEquals('test1/test2', $route['check1'], "Single compound pocket did not match");
        } else {
            $this->assertEquals('test1', $route['check1'], "First of two pockets did not match");
            $this->assertEquals('test2', $route['check2'], "Second of two pockets did not match");
        }
    }

    /**
     * Provide uri's and corresponding patterns for testBindVarSingleUrlPart()
     */
    public function bindVarSingleUrlPartProvider()
    {
        return [
            ['/*', '/test', ['controller' => 'value', 'check' => '$1']],
            ['/foo/*/bar', '/foo/test/bar', ['controller' => 'value', 'check' => '$2']],
            ['/foo/bar/*', '/foo/bar/test', ['controller' => 'value', 'check' => '$3']],
            ['/foo/bar/*/zet/*', '/foo/bar/test1/zet/test2', ['controller' => 'value', 'check1' => '$3', 'check2' => '$5']],
            ['/foo/bar/*/zet/*', '/foo/bar/test1/zet/test2', ['controller' => 'value', 'check1' => '~$3~/~$5~']]
        ];
    }

    /**
     * Test binding multyple url parts to route option
     * 
     * @dataProvider bindVarMultipleUrlPartsProvider
     * @param string $uri 
     * @param array $options     Route options
     * @param boolean $positive  
     * @param string $exception 
     */
    public function testBindVarMultipleUrlParts($uri, $options, $positive, $exception)
    {
        if ($exception) $this->expectException(InvalidArgumentException::class);

        $values = [$uri => $options];
        $glob = new Glob($values);
        $request = $this->getServerRequest($uri);        
        $route = $glob->getRoute($request);

        if ($exception) return;

        $values = explode('/', trim($uri, '/'));
        $this->assertTrue((bool)$route, "Route not found");

        $positive ?
            $this->assertArraysEqual($values, $route['check'], "Multyple url parts are not picked correctly") :
            $this->assertEmpty($route['check'], "Multyple parts element should be empty");

        if (!empty($options['check2'])) {
            array_shift($values);
            $this->assertArraysEqual($values, $route['check2'], "Secondary multyple url parts are not picked correctly");
        }
    }

    /**
     * Provide uri's and corresponding patterns for testBindVarMultipleUrlParts()
     */
    public function bindVarMultipleUrlPartsProvider()
    {
        return [
            ['/foo', ['controller' => 'value', 'check' => '$1...'], false, InvalidArgumentException::class],
            ['/', ['controller' => 'value', 'check' => ['$1...']], false, ''],
            ['/foo', ['controller' => 'value', 'check' => ['$1...']], true, ''],
            ['/foo/bar', ['controller' => 'value', 'check' => ['$1...'], 'check2' => ['$2...']], true, '']
        ];
    }

    /**
     * Test binding element of superglobal array to route option
     *
     * @dataProvider bindVarSuperGlobalProvider
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

        $this->assertEquals($test['check'], $route['check'], "Did not obtaine value for superglobal '$type'");
    }

    /**
     * Provide uri's and corresponding patterns for testBindVarMultipleUrlParts()
     */
    public function bindVarSuperGlobalProvider()
    {
        return [
            ['/foo', ['controller' => 'value', 'check' => '$_GET[check]'], 'get'],
            ['/foo', ['controller' => 'value', 'check' => '$_POST[check]'], 'post'],
            ['/foo', ['controller' => 'value', 'check' => '$_COOKIE[check]'], 'cookie']
        ];
    }

    /**
     * Test binding element of superglobal array to route option
     */
    public function testBindVarRequestHeader()
    {   
        $uri = '/foo/bar';
        $test = 'test_header_value';
        $glob = new Glob([$uri => ['controller' => 'value', 'check' => '$HTTP_REFERER']]);
        $request = $this->getServerRequest($uri, 'GET', [], $test);
        $route = $glob->getRoute($request);

        $this->assertEquals($test, $route['check'], "Did not obtaine value for header");
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
        $this->assertEquals(count($array1), count($array2));
        $this->assertEmpty(array_diff($array1, $array2));
    }
}
