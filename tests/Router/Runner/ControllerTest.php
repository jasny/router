<?php

namespace Jasny\Router;

use Jasny\Router\Route;
use Jasny\Router\Runner;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

use Jasny\Router\TestHelpers;

/**
 * @covers Jasny\Router\Runner\Controller
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
        return [
            [
                null,
                'foo-bar-zoo',
                "Can't route to controller 'FooBarZooController': class not exists"
            ],
            [
                null,
                ['foo', 'BAR', 'zoo'],
                "Can't route to controller 'Foo\Bar\ZooController': class not exists"
            ],
            [
                'stdClass',
                'foo',
                "Can't route to controller 'stdClass': class does not have '__invoke' method"
            ],
            [
                'StDclass',
                'foo',
                "Can't route to controller 'StDclass': case mismatch with 'stdClass'"
            ],
            [
                null,
                'fooBarZoo',
                "Can't route to controller 'FoobarzooController': class not exists"
            ],
            [
                null,
                '-foo-bar-zoo',
                "Can't route to controller '-fooBarZooController': invalid classname"
            ],
            [
                null,
                'foo--bar-zoo',
                "Can't route to controller 'Foo--barZooController': invalid classname"
            ],
        ];
    }
    
    /**
     * @dataProvider invalidProvider
     * 
     * @param string       $class
     * @param string|array $controller
     * @param string       $message
     */
    public function testInvokeInvalid($class, $controller, $message)
    {
        if (empty($class)) {
            $runner = $this->createPartialMock(Runner\Controller::class, ['instantiate']);
        } else {
            $runner = $this->createPartialMock(Runner\Controller::class, ['instantiate', 'getClass']);
            $runner->expects($this->once())->method('getClass')->with('foo')->willReturn($class);
        }
        $runner->expects($this->never())->method('instantiate');
        
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
        $route->controller = $controller;
        
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
        
        $result = @$runner($request, $response);
        
        $this->assertSame($notFound, $result);
        $this->assertLastError(E_USER_NOTICE, $message);
    }
}
