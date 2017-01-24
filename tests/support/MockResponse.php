<?php

namespace Jasny\Router;

use PHPUnit_Framework_MockObject_Matcher_Invocation as Invocation;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Helper methods for PHPUnit tests
 */
trait TestHelpers
{
    /**
     * Create mock for next callback
     * 
     * @param Invocation  $matcher
     * @param array       $with     With arguments
     * @param mixed       $return
     * @return MockObject
     */
    protected function createCallbackMock(Invocation $matcher, $with = [], $return = null)
    {
        $callback = $this->getMockBuilder(\stdClass::class)->setMethods(['__invoke'])->getMock();
        $callback->expects($matcher)->method('__invoke')
            ->with(...$with)
            ->willReturn($return);
        
        return $callback;
    }
    
    /**
     * Assert a non-fatal error
     * 
     * @param int    $type
     * @param string $message
     */
    protected function assertLastError($type, $message)
    {
        $error = error_get_last();
        
        $expect = compact('type', 'message');
        
        if (is_array($error)) {
            $error = array_intersect_key($error, $expect);
        }
        
        $this->assertEquals($expect, $error);
    }
    
    /**
     * Mock the calls for a 404 Not Found response
     * 
     * @return array [request, response, expect]
     */
    protected function mockNotFound()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getProtocolVersion')->willReturn('1.1');
        
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->once())->method('write')->with('Not found');
        
        $expect = $this->createMock(ResponseInterface::class);
        $expect->expects($this->once())->method('getBody')->willReturn($stream);
        
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('withProtocolVersion')->with('1.1')->willReturnSelf();
        $response->expects($this->once())->method('withStatus')->with(404)->willReturnSelf();
        $response->expects($this->once())->method('withHeader')->with('Content-Type', 'text/plain')
            ->willReturn($expect);
        
        return [$request, $response, $expect];
    }
}
