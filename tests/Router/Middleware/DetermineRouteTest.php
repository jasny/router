<?php

use Jasny\Router\Route;
use Jasny\Router\RoutesInterface;
use Jasny\Router\Middleware\DetermineRoute;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use Jasny\TestHelper;

/**
 * @covers Jasny\Router\Middleware\DetermineRoute
 */
class DetermineRouteTest extends PHPUnit_Framework_TestCase
{
    use TestHelper;
    
    public function testConstruct()
    {
        $routes = $this->createMock(RoutesInterface::class);
        $middelware = new DetermineRoute($routes);
        
        $this->assertSame($routes, $middelware->getRoutes());
    }
    
    public function routeProvider()
    {
        return [
            [$this->createMock(Route::class)],
            [null]
        ];
    }

    /**
     * @dataProvider routeProvider
     * 
     * @param Route|null $route
     */
    public function testInvoke($route)
    {
        $requestWithRoute = $this->createMock(ServerRequestInterface::class);
        
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())->method('withAttribute')->with('route', $route)
            ->willReturn($requestWithRoute);
        
        $response = $this->createMock(ResponseInterface::class);
        
        $next = $this->createCallbackMock($this->once(), [$requestWithRoute, $response]);
        
        $routes = $this->createMock(RoutesInterface::class);
        $routes->expects($this->once())->method('getRoute')->with($request)->willReturn($route);
        
        $middelware = new DetermineRoute($routes);
        
        $middelware($request, $response, $next);
    }
    
    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvokeWithInvalidCallback()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        
        $routes = $this->createMock(RoutesInterface::class);
        $middelware = new DetermineRoute($routes);
        
        $middelware($request, $response, 'not a function');
    }
}
