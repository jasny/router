<?php

use Jasny\Router\Routes;
use Jasny\Router\Middleware\NotFound;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @covers Jasny\Router\Middleware\NotFound
 */
class NotFoundTest extends PHPUnit_Framework_TestCase
{
    public function invalidStatusProvider()
    {
        return [
            [0],
            [true],
            ['foo bar zoo'],
            [1000],
            [['abc']]
        ];
    }

    /**
     * @dataProvider invalidStatusProvider
     * @expectedException InvalidArgumentException
     * 
     * @param string $status
     */
    public function testConstructInvalidNotFound($status)
    {
        new NotFound($this->createMock(Routes::class), $status);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructNotFoundNotNull()
    {
        new NotFound($this->createMock(Routes::class), null);
    }

    /**
     * @dataProvider invalidStatusProvider
     * @expectedException InvalidArgumentException
     * 
     * @param string $status 
     */
    public function testConstructInvalidMethodNotAllowed($status)
    {
        new NotFound($this->createMock(Routes::class), 404, $status);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvokeInvalidNext()
    {   
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        
        $middleware = new NotFound($this->createMock(Routes::class));

        $middleware($request, $response, 'foo bar zoo');
    }

    
    /**
     * Provide data for testing invoke method
     */
    public function invokeProvider()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        
        $mockCallback = function() {
            return $this->getMockBuilder(\stdClass::class)->setMethods(['__invoke'])->getMock();
        };
        
        return [
            [clone $request, clone $response, 404, 405, $mockCallback()],
            [clone $request, clone $response, 404, null, $mockCallback()],
            [clone $request, clone $response, '200', '402', $mockCallback()],
            [clone $request, clone $response, $mockCallback(), $mockCallback(), $mockCallback()],
            [clone $request, clone $response, $mockCallback(), null, $mockCallback()]
        ];
    }

    /**
     * Test that 'next' callback is invoked when route is found
     * @dataProvider invokeProvider
     * 
     * @param ServerRequestInterface|MockObject $request
     * @param ResponseInterface|MockObject      $response
     * @param callback|MockObject|int           $notFound 
     * @param callback|MockObject|int           $methodNotAllowed 
     * @param callback|MockObject               $next
     */
    public function testInvokeFound($request, $response, $notFound, $methodNotAllowed, $next)
    {
        $finalResponse = $this->createMock(ResponseInterface::class);
        
        if ($notFound instanceof MockObject) {
            $notFound->expects($this->never())->method('__invoke');
        }
        
        if ($methodNotAllowed instanceof MockObject) {
            $methodNotAllowed->expects($this->never())->method('__invoke');
        }
        
        $next->expects($this->once())->method('__invoke')->with($request, $response)->willReturn($finalResponse);
        
        $response->expects($this->never())->method('withStatus');
        
        $routes = $this->createMock(Routes::class);
        $routes->expects($this->once())->method('hasRoute')->with($request)->willReturn(true);
        
        $middleware = new NotFound($routes, $notFound, $methodNotAllowed);

        $result = $middleware($request, $response, $next);

        $this->assertSame($finalResponse, $result);
    }

    /**
     * Test __invoke method in case of route is found with another method
     * @dataProvider invokeProvider
     * 
     * @param ServerRequestInterface|MockObject $request
     * @param ResponseInterface|MockObject      $response
     * @param callback|MockObject|int           $notFound 
     * @param callback|MockObject|int           $methodNotAllowed 
     * @param callback|MockObject               $next
     */
    public function testInvokeNotFound($request, $response, $notFound, $methodNotAllowed, $next)
    {
        $finalResponse = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        
        if ($notFound instanceof MockObject) {
            $notFound->expects($this->once())->method('__invoke')
                ->with($request, $response)
                ->willReturn($finalResponse);
            
            $response->expects($this->never())->method('withStatus');
        } else {
            $response->expects($this->once())->method('withStatus')
                ->with($notFound)
                ->willReturn($finalResponse);
            
            $finalResponse->expects($this->once())->method('getBody')->willReturn($stream);
            $stream->expects($this->once())->method('write')->with('Not found');
        }
        
        if ($methodNotAllowed instanceof MockObject) {
            $methodNotAllowed->expects($this->never())->method('__invoke');
        }
        
        $next->expects($this->never())->method('__invoke');
        
        $routes = $this->createMock(Routes::class);
        
        $routes->expects($this->exactly(isset($methodNotAllowed) ? 2 : 1))->method('hasRoute')
            ->withConsecutive([$request], [$request, false])
            ->willReturn(false);
        
        $middleware = new NotFound($routes, $notFound, $methodNotAllowed);

        $result = $middleware($request, $response, $next);

        $this->assertSame($finalResponse, $result);
    }

    /**
     * Test __invoke method in case of route is found with another method
     * @dataProvider invokeProvider
     * 
     * @param ServerRequestInterface|MockObject $request
     * @param ResponseInterface|MockObject      $response
     * @param callback|MockObject|int           $notFound 
     * @param callback|MockObject|int           $methodNotAllowed 
     * @param callback|MockObject               $next
     */
    public function testInvokeMethodNotAllowed($request, $response, $notFound, $methodNotAllowed, $next)
    {
        $finalResponse = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        
        $expect = $methodNotAllowed ?: $notFound;
        
        if ($expect !== $notFound && $notFound instanceof MockObject) {
            $notFound->expects($this->never())->method('__invoke');
        }
        
        if ($expect instanceof MockObject) {
            $expect->expects($this->once())->method('__invoke')
                ->with($request, $response)
                ->willReturn($finalResponse);
            
            $response->expects($this->never())->method('withStatus');
        } else {
            $response->expects($this->once())->method('withStatus')
                ->with($expect)
                ->willReturn($finalResponse);
            
            $finalResponse->expects($this->once())->method('getBody')->willReturn($stream);
            $stream->expects($this->once())->method('write')->with('Not found');
        }
        
        $next->expects($this->never())->method('__invoke');
        
        $routes = $this->createMock(Routes::class);
        
        $routes->expects($this->exactly(isset($methodNotAllowed) ? 2 : 1))->method('hasRoute')
            ->withConsecutive([$request], [$request, false])
            ->will($this->onConsecutiveCalls(false, true));
        
        $middleware = new NotFound($routes, $notFound, $methodNotAllowed);

        $result = $middleware($request, $response, $next);

        $this->assertSame($finalResponse, $result);
    }
}
