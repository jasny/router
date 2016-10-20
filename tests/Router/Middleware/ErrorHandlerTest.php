<?php

use Jasny\Router\Middleware\ErrorHandler;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class ErrorHandlerTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test invoke with invalid 'next' param
     */
    public function testInvokeInvalidNext()
    {
        $middleware = new ErrorHandler();
        list($request, $response) = $this->getRequests();

        $this->expectException(\InvalidArgumentException::class);

        $result = $middleware($request, $response, 'not_callable');
    }

    /**
     * Test that exception in 'next' callback is caught
     */
    public function testInvokeCatchError()
    {
        $middleware = new ErrorHandler();
        list($request, $response) = $this->getRequests();

        $this->expectCatchError($response);

        $result = $middleware($request, $response, function($request, $response) {
            throw new Exception('Test exception'); 
        });

        $this->assertEquals(get_class($response), get_class($result), "Middleware should return response object");        
    }

    /**
     * Test case when there is no error
     */
    public function testInvokeNoError()
    {
        $middleware = new ErrorHandler();
        list($request, $response) = $this->getRequests();

        $result = $middleware($request, $response, function($request, $response) {
            $response->nextCalled = true;

            return $response;
        });        

        $this->assertEquals(get_class($response), get_class($result), "Middleware should return response object");        
        $this->assertTrue($result->nextCalled, "'next' was not called");   
    }

    /**
     * Get requests for testing
     *
     * @return array
     */
    public function getRequests()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        return [$request, $response];
    }

    /**
     * Expect for error
     *
     * @param ResponseInterface $response 
     */
    public function expectCatchError($response)
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->once())->method('rewind');
        $stream->expects($this->once())->method('write')->with($this->equalTo('Unexpected error'));

        $response->method('getBody')->will($this->returnValue($stream));
        $response->expects($this->once())->method('withBody')->with($this->equalTo($stream))->will($this->returnSelf());
        $response->expects($this->once())->method('withStatus')->with($this->equalTo(500), $this->equalTo('Internal Server Error'))->will($this->returnSelf());
    }
}
