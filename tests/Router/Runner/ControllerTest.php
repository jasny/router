<?php

namespace Jasny\Router;

use Jasny\Router\Route;
use Jasny\Router\Runner;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

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
    
    public function invalidProvider()
    {
        $runnerNotExists = $this->createPartialMock(Runner\Controller::class, ['instantiate']);
        $runnerNotExists->expects($this->never())->method('instantiate');
        
        $runnerNotCallable = $this->createPartialMock(Runner\Controller::class, ['instantiate', 'getClass']);
        $runnerNotCallable->expects($this->once())->method('getClass')->with('foo-bar-zoo')->willReturn('stdClass');
        $runnerNotCallable->expects($this->never())->method('instantiate');
        
        return [
            [
                $runnerNotExists,
                "Can't route to controller 'FooBarZooController': class not exists"
            ],
            [
                $runnerNotCallable,
                "Can't route to controller 'stdClass': class does not have '__invoke' method"
            ]
        ];
    }
    
    /**
     * @dataProvider invalidProvider
     * 
     * @param Runner $runner
     * @param string $message
     */
    public function testInvokeInvalid(Runner $runner, $message)
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getProtocolVersion')->willReturn('1.1');
        
        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())->method('write')->with('Not found');
        
        $notFound = $this->createMock(ResponseInterface::class);
        $notFound->expects($this->once())->method('getBody')->willReturn($body);
        
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('withProtocolVersion')->with('1.1')->willReturnSelf();
        $response->expects($this->once())->method('withStatus')->with(404)->willReturnSelf();
        $response->expects($this->once())->method('withHeader')->with('Content-Type', 'text/plain')
            ->willReturn($notFound);
        
        $route = $this->createMock(Route::class);
        $route->controller = 'foo-bar-zoo';
        
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
        
        $result = @$runner($request, $response);
        
        $this->assertSame($notFound, $result);
        $this->assertLastError(E_USER_NOTICE, $message);
    }
}
