<?php

namespace Jasny\Router\Routes;

use Jasny\Router\Routes\Glob;
use Jasny\Router\Route;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use InvalidArgumentException;

/**
 * @covers Jasny\Router\Routes\RouteBinding
 */
class RouteBindingTest extends \PHPUnit_Framework_TestCase
{
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
            ['/', '/', ['check' => '$foo'], null],
            ['/', '/', ['check' => 'test', 'checkB' => null], 'test', null]
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
        } else {
            $this->assertObjectNotHasAttribute('checkB', $route);
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
        $uriMock = $this->createMock(UriInterface::class);
        $uriMock->method('__toString')->willReturn("http://www.example.com" . $uri);
        $uriMock->method('getPath')->willReturn($uri);
        
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uriMock);
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
