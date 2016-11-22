<?php

namespace Jasny\Router;

use Jasny\Router\Route;
use Jasny\Router\Runner;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use Jasny\Router\TestHelpers;

/**
 * @covers Jasny\Router\Runner\Controller;
 */
class ControllerTest extends \PHPUnit_Framework_TestCase
{
    use TestHelpers;
    
    public function testInvoke()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $finalResponse = $this->createMock(ResponseInterface::class);

        $controller = $this->createCallbackMock($this->once(), [$request, $response], $finalResponse);
        $class = get_class($controller);
        
        $route = $this->createMock(Route::class);
        $route->controller = 'foo';
        
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
        
        $runner = $this->getMockBuilder(Runner\Controller::class)->setMethods(['instantiate', 'getClass'])->getMock();
        $runner->expects($this->once())->method('getClass')->with('foo')->willReturn($class);
        $runner->expects($this->once())->method('instantiate')->with($class)->willReturn($controller);

        $result = $runner($request, $response);
        
        $this->assertSame($finalResponse, $result);
    }
    
    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Can not route to controller 'DoesNotExistsController': class not exists
     */
    public function testInvalidClass()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        
        $route = $this->createMock(Route::class);
        $route->controller = 'does-not-exists';
        
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
        
        $runner = $this->getMockBuilder(Runner\Controller::class)->setMethods(['instantiate'])->getMock();
        $runner->expects($this->never())->method('instantiate');

        $runner($request, $response);
    }
    
    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Can not route to controller 'stdClass': class does not have '__invoke' method
     */
    public function testInvokeNotCallable()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        
        $route = $this->createMock(Route::class);
        $route->controller = 'foo';
        
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
        
        $runner = $this->getMockBuilder(Runner\Controller::class)->setMethods(['instantiate', 'getClass'])->getMock();
        $runner->expects($this->once())->method('getClass')->with('foo')->willReturn('stdClass');
        $runner->expects($this->never())->method('instantiate');

        $runner($request, $response);
    }
}
