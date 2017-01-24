<?php

namespace Jasny\Router\Runner;

use Jasny\Router\Route;
use Jasny\Router\Runner;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Jasny\TestHelper;
use Jasny\Router\MockResponse;

/**
 * @covers Jasny\Router\Runner\Delegate
 * @covers Jasny\Router\Helper\NotFound
 */
class DelegateTest extends \PHPUnit_Framework_TestCase
{
    use TestHelper;
    use MockResponse;
    
    public function testInvoke()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $finalResponse = $this->createMock(ResponseInterface::class);

        $next = $this->createCallbackMock($this->never());
        $mockRunner = $this->createCallbackMock($this->once(), [$request, $response, $next], $finalResponse);
        
        $route = $this->createMock(Route::class);
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
        
        $runner = $this->createPartialMock(Runner\Delegate::class, ['getRunner']);
        $runner->expects($this->once())->method('getRunner')->with($route)->willReturn($mockRunner);

        $result = $runner($request, $response, $next);
        
        $this->assertSame($finalResponse, $result);
    }
    
    public function testInvokeNoRoute()
    {
        list($request, $response, $notFoundResponse) = $this->mockNotFound();

        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn(null);
        
        $runner = $this->createPartialMock(Runner\Delegate::class, ['getRunner']);
        $runner->expects($this->never())->method('getRunner');
        
        $result = @$runner($request, $response);
        
        $this->assertLastError(E_USER_NOTICE, "Route on request isn't set");
        $this->assertSame($notFoundResponse, $result);
    }
    
    public function testInvokeInvalidRoute()
    {
        list($request, $response, $notFoundResponse) = $this->mockNotFound();

        $route = $this->createMock(Route::class);
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
        
        $runner = new Runner\Delegate();
        
        $result = @$runner($request, $response);
        
        $this->assertLastError(E_USER_NOTICE, "Route has neither 'controller', 'fn' or 'file' defined");
        $this->assertSame($notFoundResponse, $result);
    }
    
    
    public function runnerProvider()
    {
        return [
            ['controller', Runner\Controller::class],
            ['fn', Runner\Callback::class],
            ['file', Runner\PhpScript::class]
        ];
    }
    
    /**
     * @dataProvider runnerProvider
     * 
     * @param string $property
     * @param string $class
     */
    public function testGetRunner($property, $class)
    {
        $route = $this->createMock(Route::class);
        $route->$property = 'foo';
        
        $runner = new Runner\Delegate();
        $result = $runner->getRunner($route);
        
        $this->assertInstanceOf($class, $result);
    }
}
