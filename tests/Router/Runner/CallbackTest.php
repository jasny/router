<?php

namespace Jasny\Router\Runner;

use Jasny\Router\Route;
use Jasny\Router\Runner;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
    
use Jasny\Router\TestHelpers;

/**
 * @covers Jasny\Router\Runner\Callback
 * @covers Jasny\Router\Runner\Implementation
 * @covers Jasny\Router\Helpers\NotFound
 */
class CallbackTest extends \PHPUnit_Framework_TestCase
{
    use TestHelpers;
    
    public function testInvoke()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $finalResponse = $this->createMock(ResponseInterface::class);
        
        $route = $this->createMock(Route::class);
        $route->fn = $this->createCallbackMock($this->once(), [$request, $response], $finalResponse);
        
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
            
        $runner = new Runner\Callback($route);
        $result = $runner($request, $response);
        
        $this->assertSame($finalResponse, $result);
    }
    
    public function testInvokeWithNext()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $runResponse = $this->createMock(ResponseInterface::class);
        $finalResponse = $this->createMock(ResponseInterface::class);

        $route = $this->createMock(Route::class);
        $route->fn = $this->createCallbackMock($this->once(), [$request, $response], $finalResponse);
        
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
        
        $next = $this->createCallbackMock($this->once(), [$request, $runResponse], $finalResponse);
        
        $runner = new Runner\Callback($route);

        $result = $runner($request, $response, $next);
        
        $this->assertSame($finalResponse, $result);
    }
    
    
    public function invalidCallbackProvider()
    {
        return [
            [],
            ['foo bar zoo']
        ];
    }
    
    /**
     * @dataProvider invalidCallbackProvider
     * 
     * @param string $fn
     */
    public function testInvalidCallback($fn = null)
    {
        list($request, $response, $notFoundResponse) = $this->mockNotFound();
        
        $route = $this->createMock(Route::class);
        
        if (isset($fn)) {
            $route->fn = $fn;
        }
        
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
        
        $runner = new Runner\Callback($route);
        $result = @$runner($request, $response);
        
        $this->assertLastError(E_USER_NOTICE, "'fn' property of route shoud be a callable");
        
        $this->assertSame($notFoundResponse, $result);
    }
}
