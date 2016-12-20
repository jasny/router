<?php

namespace Jasny\Router\Runner;

use Jasny\Router\Route;
use Jasny\Router\Runner;
use Jasny\Router\ControllerFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use Jasny\Router\TestHelpers;

/**
 * @covers Jasny\Router\Runner\Controller
 * @covers Jasny\Router\Runner\Implementation
 * @covers Jasny\Router\Helpers\NotFound
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
        $factory = $this->createCallbackMock($this->once(), ['foo'], $controller);
        
        $route = $this->createMock(Route::class);
        $route->controller = 'foo';
        
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
        
        $runner = (new Runner\Controller())->withFactory($factory);

        $result = $runner($request, $response);
        
        $this->assertSame($finalResponse, $result);
    }
    
    public function testInvokeWithNext()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $runResponse = $this->createMock(ResponseInterface::class);
        $finalResponse = $this->createMock(ResponseInterface::class);

        $controller = $this->createCallbackMock($this->once(), [$request, $response], $finalResponse);
        $factory = $this->createCallbackMock($this->once(), ['foo'], $controller);
        
        $route = $this->createMock(Route::class);
        $route->controller = 'foo';
        
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
        
        $next = $this->createCallbackMock($this->once(), [$request, $runResponse], $finalResponse);
        
        $runner = (new Runner\Controller())->withFactory($factory);

        $result = $runner($request, $response, $next);
        
        $this->assertSame($finalResponse, $result);
    }

    public function testInvokeWithFactoryException()
    {
        list($request, $response, $notFoundResponse) = $this->mockNotFound();
        
        $exception = new \Exception('Something is wrong');
        
        $factory = $this->createPartialMock('stdClass', ['__invoke']);
        $factory->expects($this->once())->method('__invoke')->willThrowException($exception);
        
        $runner = (new Runner\Controller())->withFactory($factory);
        
        $result = @$runner($request, $response);
        
        $this->assertLastError(E_USER_NOTICE, 'Something is wrong');
        $this->assertSame($notFoundResponse, $result);
    }
    
    public function testInvokeWithUncallableController()
    {
        list($request, $response, $notFoundResponse) = $this->mockNotFound();
        
        $factory = $this->createPartialMock('stdClass', ['__invoke']);
        $factory->expects($this->once())->method('__invoke')->willReturn(new \stdClass());
        
        $runner = (new Runner\Controller())->withFactory($factory);
        
        $result = @$runner($request, $response);
        
        $this->assertLastError(E_USER_NOTICE,
            "Can't route to controller 'stdClass': class does not have '__invoke' method");
        
        $this->assertSame($notFoundResponse, $result);
    }
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidFactory()
    {
        (new Runner\Controller())->withFactory('not callable');
    }
    
    public function testGetDefaultFactory()
    {
        $runner = new Runner\Controller();
        $factory = $runner->getFactory();
        
        $this->assertInstanceOf(ControllerFactory::class, $factory);
        $this->assertSame($factory, $runner->getFactory());
    }
}
