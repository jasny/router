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
        $this->assertEquals($routes, $router->getRoutes());
    }

    /**
     * Test that on router 'run', method '__invoke' is called
     */
    public function testRun()
    {
        $router = $this->createMock(Router::class, ['__invoke']);
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $router->method('__invoke')->will($this->returnCallback(function($arg1, $arg2) {
            return ['request' => $arg1, 'response' => $arg2];
        }));

        $result = $router->run($request, $response);

        $this->assertEquals($request, $result['request']);
        $this->assertEquals($response, $result['response']);
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

        $this->assertEquals($request, $result['request']);
        $this->assertEquals($response, $result['response']);
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

        $this->assertEquals($request, $result['request']);
        $this->assertEquals($response, $result['response']);
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

        $this->assertEquals(get_class($response), get_class($result));
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
