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
        $route->controller = $class;
        
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
        
        $runner = $this->getMockBuilder(Runner\Controller::class)->setMethods(['instantiate'])->getMock();
        $runner->expects($this->once())->method('instantiate')->with($class)->willReturn($controller);

        $result = $runner($request, $response);
        
        $this->assertSame($finalResponse, $result);
    }
    
    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Can not route to controller 'FooBarZoo': class not exists
     */
    public function testInvalidClass()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        
        $route = $this->createMock(Route::class);
        $route->controller = 'foo-bar-zoo';
        
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
        
        $runner = $this->getMockBuilder(Runner\Controller::class)->setMethods(['instantiate'])->getMock();
        $runner->expects($this->never())->method('instantiate');

        $runner($request, $response);
    }
    
    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Can not route to controller 'StdClass': class does not have '__invoke' method
     */
    public function testInvokeNotCallable()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        
        $route = $this->createMock(Route::class);
        $route->controller = 'std-class';
        
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
        
        $runner = $this->getMockBuilder(Runner\Controller::class)->setMethods(['instantiate'])->getMock();
        $runner->expects($this->never())->method('instantiate');

        $runner($request, $response);
    }
}
