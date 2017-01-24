<?php

use Jasny\RouterInterface;
use Jasny\Router\Route;
use Jasny\Router\RoutesInterface;
use Jasny\Router\Middleware\ErrorPage;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

use Jasny\TestHelper;

class ErrorPageTest extends PHPUnit_Framework_TestCase
{
    use TestHelper;
    
    public function testGetRouter()
    {
        $router = $this->createMock(RouterInterface::class);
        $middleware = new ErrorPage($router);
        
        $this->assertSame($router, $middleware->getRouter());
    }
    
    /**
     * Test invoke with invalid 'next' param
     * 
     * @expectedException InvalidArgumentException
     */
    public function testInvokeInvalidNext()
    {
        $router = $this->createMock(RouterInterface::class);
        $middleware = new ErrorPage($router);
        
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $middleware($request, $response, 'not callable');
    }

    /**
     * Provide data for testing '__invoke' method
     *
     * @return array
     */
    public function invokeProvider()
    {
        return [
            [200, 0, 0],
            [300, 0, 0],
            [400, 1, 1],
            [404, 1, 1],
            [500, 1, 1],
            [503, 1, 1],
            [400, 1, 0]
        ];
    }

    /**
     * Test error and not-error cases with calling 'next' callback
     * @dataProvider invokeProvider
     * 
     * @param int $statusCode
     * @param int $invoke
     * @param int $run
     */
    public function testInvokeNext($statusCode, $invoke, $run)
    {
        $errorUri = $this->createMock(UriInterface::class);
        $errorRequest = $this->createMock(ServerRequestInterface::class);
        $errorResponse = $this->createMock(ResponseInterface::class);

        $route = $this->createMock(Route::class);
        $routes = $this->createMock(RoutesInterface::class);
        $routes->method('getRoute')->with($errorRequest)->willReturn($run ? $route : null);
        
        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->exactly($invoke))->method('withPath')->with($statusCode)->willReturnSelf();
        $uri->expects($this->exactly($invoke))->method('withQuery')->with(null)->willReturnSelf();
        $uri->expects($this->exactly($invoke))->method('withFragment')->with(null)->willReturn($errorUri);
        
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->expects($this->exactly($invoke))->method('withUri')->with($errorUri)->willReturn($errorRequest);
        $request->expects($this->exactly($run))->method('withAttribute')->with('route', $route)
            ->willReturn($errorRequest);

        $runnerRequest = $this->createMock(ServerRequestInterface::class);
        
        $response = $this->createMock(ResponseInterface::class);
        
        $nextResponse = $this->createMock(ResponseInterface::class);
        $nextResponse->method('getStatusCode')->willReturn($statusCode);
        
        $next = $this->createCallbackMock($this->once(), [], $nextResponse);
        
        $runner = $this->createCallbackMock($this->exactly($run), [$runnerRequest, $nextResponse], $errorResponse);
        
        $router = $this->createMock(RouterInterface::class);
        $router->method('getRoutes')->willReturn($routes);
        $router->method('getRunner')->willReturn($runner);
        
        $middleware = new ErrorPage($router);
        
        $result = $middleware($request, $response, $next);
        
        $this->assertSame($run === 0 ? $nextResponse : $errorResponse, $result);
    }
}
