<?php

namespace Jasny;

use Jasny\Router;
use Jasny\Router\Route;
use Jasny\Router\Routes;
use Jasny\Router\RunnerFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

use Jasny\Router\TestHelpers;

/**
 * @covers Jasny\Router
 */
class RouterTest extends \PHPUnit_Framework_TestCase
{
    use TestHelpers;
    
    /**
     * Test creating Router
     */
    public function testGetRoutes()
    {   
        $routes = $this->createMock(Routes::class);

        $router = new Router($routes);
        $this->assertSame($routes, $router->getRoutes(), "Routes were not set correctly");
    }

    
    /**
     * Test getting runner factory
     */
    public function testGetFactory()
    {
        $router = new Router($this->createMock(Routes::class));
        $factory = $router->getFactory();

        $this->assertInstanceOf(RunnerFactory::class, $factory);
    }
    
    /**
     * Test setting runner factory
     */
    public function testSetFactory()
    {
        $factoryMock = $this->createCallbackMock($this->never());
        
        $router = new Router($this->createMock(Routes::class));
        
        $ret = $router->setFactory($factoryMock);
        $this->assertSame($router, $ret);
        
        $this->assertSame($factoryMock, $router->getFactory());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetInvalidFactory()
    {
        $router = new Router($this->createMock(Routes::class));
        $router->setFactory('foo bar zoo');
    }

    
    /**
     * Test that on router 'handle', method '__invoke' is called
     */
    public function testHandle()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $finalResponse = $this->createMock(ResponseInterface::class);
        
        $router = $this->getMockBuilder(Router::class)->disableOriginalConstructor()
            ->setMethods(['__invoke'])->getMock();
        $router->expects($this->once())->method('__invoke')->with($request, $response)->willReturn($finalResponse);

        $result = $router->handle($request, $response);

        $this->assertSame($finalResponse, $result);
    }

    public function nextProvider()
    {
        return [
            [null],
            [$this->createCallbackMock($this->any())]
        ];
    }

    /**
     * Test '__invoke' method
     * 
     * @dataProvider nextProvider
     */
    public function testInvoke($next)
    {
        $route = $this->createMock(Route::class);
        
        $request = $this->createMock(ServerRequestInterface::class);
        $requestWithRoute = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())->method('withAttribute')->with('route')->willReturn($requestWithRoute);
        
        $response = $this->createMock(ResponseInterface::class);
        $finalResponse = $this->createMock(ResponseInterface::class);
        
        $runner = $this->createCallbackMock($this->once(), [$requestWithRoute, $response, $next], $finalResponse);
        $factory = $this->createCallbackMock($this->once(), [$route], $runner);

        $routes = $this->createMock(Routes::class);
        $routes->expects($this->once())->method('getRoute')->with($request)->willReturn($route);

        $router = new Router($routes);
        $router->setFactory($factory);
        
        $result = $router($request, $response, $next);
        
        $this->assertSame($finalResponse, $result);
    }

    /**
     * Test case when route is not found
     */
    public function testNotFound()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $finalResponse = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);

        $response->expects($this->once())->method('withStatus')->with(404)->willReturn($finalResponse);
        $finalResponse->expects($this->once())->method('getBody')->willReturn($body);
        $body->expects($this->once())->method('write')->with('Not Found');
            
        $factory = $this->createCallbackMock($this->never());

        $routes = $this->createMock(Routes::class);
        $routes->expects($this->once())->method('getRoute')->with($request)->willReturn(null);

        $router = new Router($routes);
        $router->setFactory($factory);
        
        $result = $router($request, $response);
        
        $this->assertSame($finalResponse, $result);
    }

    /**
     * Test adding middleware action
     */
    public function testAdd()
    {
        $middlewareOne = $this->createCallbackMock($this->never());
        $middlewareTwo = $this->createCallbackMock($this->never());

        $router = new Router($this->createMock(Routes::class));
        
        $this->assertEquals([], $router->getMiddlewares(), "Middlewares array should be empty");

        $ret = $router->add($middlewareOne);
        $this->assertSame($router, $ret);
        
        $router->add($middlewareTwo);
        
        $this->assertSame([$middlewareOne, $middlewareTwo], $router->getMiddlewares());
    }
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddInvalidMiddleware()
    {
        $router = new Router($this->createMock(Routes::class));
        $router->add('foo bar zoo');
    }

    /**
     * Test executing router with middlewares chain
     */
    public function testRunWithMiddlewares()
    {
        $route = $this->createMock(Route::class);

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/');
        
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        
        $requestOne = $this->createMock(ServerRequestInterface::class);
        $requestOne->expects($this->never())->method('withAttribute');
        $requestTwo = $this->createMock(ServerRequestInterface::class);
        $requestTwo->expects($this->once())->method('withAttribute')->with('route')->willReturn($requestTwo);
        
        $response = $this->createMock(ResponseInterface::class);
        $responseOne = $this->createMock(ResponseInterface::class);
        $responseTwo = $this->createMock(ResponseInterface::class);
        $finalResponse = $this->createMock(ResponseInterface::class);

        $middlewareOne = $this->getMockBuilder(\stdClass::class)->setMethods(['__invoke'])->getMock();
        $middlewareOne->expects($this->once())->method('__invoke')
            ->with($request, $response, $this->isInstanceOf(\Closure::class))
            ->will($this->returnCallback(function($a, $b, $next) use ($requestOne, $responseOne) {
                return $next($requestOne, $responseOne);
            }));

        $middlewareTwo = $this->getMockBuilder(\stdClass::class)->setMethods(['__invoke'])->getMock();
        $middlewareTwo->expects($this->once())->method('__invoke')
            ->with($request, $response, $this->isInstanceOf(\Closure::class))
            ->will($this->returnCallback(function($a, $b, $next) use ($requestTwo, $responseTwo) {
                return $next($requestTwo, $responseTwo);
            }));
        
        $runner = $this->createCallbackMock($this->once(), [$requestTwo, $responseTwo], $finalResponse);
        $factory = $this->createCallbackMock($this->once(), [$route], $runner);

        $routes = $this->createMock(Routes::class);
        $routes->expects($this->once())->method('getRoute')->with($request)->willReturn($route);

        $router = new Router($routes);
        $router->setFactory($factory);
        
        $router->add($middlewareOne);
        $router->add($middlewareTwo);

        $result = $router($request, $response);
        
        $this->assertSame($finalResponse, $result);
    }

    /**
     * Test executing router with middlewares chain where each middleware is only applied for specific paths
     */
    public function testRunWithMiddlewaresByPath()
    {
        $route = $this->createMock(Route::class);

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/foo/bar');
        
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        
        $requestOne = $this->createMock(ServerRequestInterface::class);
        $requestOne->expects($this->once())->method('withAttribute')->with('route')->willReturn($requestOne);
        $requestOne->method('getUri')->willReturn($uri);
        
        $response = $this->createMock(ResponseInterface::class);
        $responseOne = $this->createMock(ResponseInterface::class);
        $finalResponse = $this->createMock(ResponseInterface::class);
        
        $middlewareOne = $this->getMockBuilder(\stdClass::class)->setMethods(['__invoke'])->getMock();
        $middlewareOne->expects($this->once())->method('__invoke')
            ->with($request, $response, $this->isInstanceOf(\Closure::class))
            ->will($this->returnCallback(function($a, $b, $next) use ($requestOne, $responseOne) {
                return $next($requestOne, $responseOne);
            }));
        
        $middlewareTwo = $this->getMockBuilder(\stdClass::class)->setMethods(['__invoke'])->getMock();
        $middlewareTwo->expects($this->never())->method('__invoke');
        
        $runner = $this->createCallbackMock($this->once(), [$requestOne, $responseOne], $finalResponse);
        $factory = $this->createCallbackMock($this->once(), [$route], $runner);

        $routes = $this->createMock(Routes::class);
        $routes->expects($this->once())->method('getRoute')->with($request)->willReturn($route);

        $router = new Router($routes);
        $router->setFactory($factory);
        
        $router->add('/foo', $middlewareOne);
        $router->add('/zoo', $middlewareTwo);
        
        $result = $router($request, $response);
        
        $this->assertSame($finalResponse, $result);
    }
}
