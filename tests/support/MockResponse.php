<?php

namespace Jasny\Router;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Helper methods for PHPUnit tests to mock a specific response
 */
trait MockResponse
{
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
