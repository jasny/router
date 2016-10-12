<?php

use Jasny\Router;
use Jasny\Router\Route;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class RouterTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test creating Router
     */
    public function testConstruct()
    {   
        $routes = [
            '/foo' => ['fn' => 'test_function'],
            '/foo/bar' => ['controller' => 'TestController']
        ];

        $router = new Router($routes);
        $this->assertEquals($routes, $router->getRoutes(), "Routes were not set correctly");
    }

    /**
     * Test that on router 'run', method '__invoke' is called
     */
    public function testRun()
    {
        $router = $this->createMock(Router::class, ['__invoke']);
        list($request, $response) = $this->getRequests();

        $router->method('__invoke')->will($this->returnCallback(function($arg1, $arg2) {
            return ['request' => $arg1, 'response' => $arg2];
        }));

        $result = $router->run($request, $response);

        $this->assertEquals($request, $result['request'], "Request was not processed correctly");
        $this->assertEquals($response, $result['response'], "Response was not processed correctly");
    }

    /**
     * Test '__invoke' method
     */
    public function testInvoke()
    {
        $routes = [
            '/foo/bar' => Route::create(['controller' => 'TestController']),
            '/foo' => Route::create(['fn' => function($arg1, $arg2) {
                return ['request' => $arg1, 'response' => $arg2];
            }])
        ];

        list($request, $response) = $this->getRequests();
        $router = new Router($routes);        
        $result = $router($request, $response);

        $this->assertEquals($request, $result['request'], "Request was not processed correctly");
        $this->assertEquals($response, $result['response'], "Response was not processed correctly");
    }

    /**
     * Test '__invoke' method with 'next' callback 
     */
    public function testInvokeNext()
    {
        $routes = [
            '/foo/bar' => Route::create(['controller' => 'TestController']),
            '/foo' => Route::create(['fn' => function($request, $response) {
                return $response;
            }])
        ];

        list($request, $response) = $this->getRequests();
        $router = new Router($routes);        
        $result = $router($request, $response, function($arg1, $arg2) {
            return ['request' => $arg1, 'response' => $arg2];
        });

        $this->assertEquals($request, $result['request'], "Request was not processed correctly");
        $this->assertEquals($response, $result['response'], "Response was not processed correctly");
    }

    /**
     * Test case when route is not found
     */
    public function testNotFound()
    {
        $routes = [
            '/foo/bar' => Route::create(['controller' => 'TestController'])
        ];

        list($request, $response) = $this->getRequests();
        $this->expectNotFound($response);        

        $router = new Router($routes);        
        $result = $router($request, $response);

        $this->assertEquals(get_class($response), get_class($result), "Returned result is not an instance of 'ServerRequestInterface'");
    }

    /**
     * Test adding middleware action
     *
     * @dataProvider addProvider
     * @param mixed $middleware1
     * @param callable $middleware2 
     * @param boolean $positive 
     */
    public function testAdd($middleware1, $middleware2, $positive)
    {
        $router = new Router([]);
        $this->assertEquals(0, count($router->getMiddlewares()), "Middlewares array should be empty");

        if (!$positive) $this->expectException(\InvalidArgumentException::class);

        $result = $router->add($middleware1);
        $this->assertEquals(1, count($router->getMiddlewares()), "There should be only one item in middlewares array");
        $this->assertEquals($middleware1, reset($router->getMiddlewares()), "Wrong item in middlewares array");
        $this->assertEquals($router, $result, "'Add' should return '\$this'");

        if (!$middleware2) return;

        $router->add($middleware2);
        $this->assertEquals(2, count($router->getMiddlewares()), "There should be two items in middlewares array");
        foreach ($router->getMiddlewares() as $action) {
            $this->assertTrue($action == $middleware1 || $action == $middleware2, "Wrong item in middlewares array");
        }
    }

    /**
     * Provide data for testing 'add' method
     */
    public function addProvider()
    {
        return [
            ['wrong_callback', null, false],
            [[$this, 'getMiddlewareCalledFirst'], null, true],
            [[$this, 'getMiddlewareCalledFirst'], [$this, 'getMiddlewareCalledLast'], true]
        ];  
    }

    /**
     * Test executing router with middlewares chain (test only execution order)
     */
    public function testRunMiddlewares()
    {
        $routes = [
            '/foo' => Route::create(['fn' => function($request, $response) {
                $response->testMiddlewareCalls[] = 'handle';
                return $response;
            }])
        ];

        list($request, $response) = $this->getRequests();
        $router = new Router($routes);
        $router->add([$this, 'getMiddlewareCalledLast'])->add([$this, 'getMiddlewareCalledFirst']);

        $result = $router($request, $response, function($request, $response) {
            $response->testMiddlewareCalls[] = 'outer';
            return $response;
        });

        $this->assertEquals(['first','last','handle','outer'], $response->testMiddlewareCalls, "Actions were executed in wrong order");
    }

    /**
     * Get requests for testing
     *
     * @return array
     */
    public function getRequests()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $request->method('getUri')->will($this->returnValue('/foo'));
        $request->method('getMethod')->will($this->returnValue('GET'));

        return [$request, $response];
    }

    /**
     * Get middleware action, that should ba called first in middleware chain
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callback               $next
     * @return ResponseInterface
     */
    public function getMiddlewareCalledFirst(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        $response->testMiddlewareCalls[] = 'first';
        return $next($request, $response);
    }

    /**
     * Get middleware action, that should be called last in middleware chain
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callback               $next
     * @return ResponseInterface
     */
    public function getMiddlewareCalledLast(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        $response->testMiddlewareCalls[] = 'last';
        return $next($request, $response);   
    }

    /**
     * Expect 'not found' response
     * 
     * @param ResponseInterface
     */
    public function expectNotFound(ResponseInterface $response)
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->once())->method('rewind');
        $stream->expects($this->once())->method('write')->with($this->equalTo('Not Found'));

        $response->method('getBody')->will($this->returnValue($stream));
        $response->expects($this->once())->method('withBody')->with($this->equalTo($stream))->will($this->returnSelf());
        $response->expects($this->once())->method('withStatus')->with($this->equalTo(404), $this->equalTo('Not Found'))->will($this->returnSelf());
    }
}
