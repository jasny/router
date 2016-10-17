<?php

use Jasny\Router;
use Jasny\Router\Routes\Glob;
use Jasny\Router\Middleware\ErrorPage;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class ErrorPageTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test invoke with invalid 'next' param
     */
    public function testInvokeInvalidNext()
    {
        $middleware = new ErrorPage($this->getRouter());
        list($request, $response) = $this->getRequests();

        $this->expectException(\InvalidArgumentException::class);

        $result = $middleware($request, $response, 'not_callable');
    }

    /**
     * Test error and not-error cases with calling 'next' callback
     *
     * @dataProvider invokeProvider
     * @param int $statusCode
     */
    public function testInvokeNext($statusCode)
    {
        $isError = $statusCode >= 400;
        $router = $this->getRouter();
        $middleware = new ErrorPage($router);
        list($request, $response) = $this->getRequests($statusCode);

        $isError ?
            $this->expectSetError($router, $request, $response, $statusCode) :
            $this->notExpectSetError($router, $request, $response, $statusCode);

        $result = $middleware($request, $response, function($request, $response) {
            $response->nextCalled = true;

            return $response;
        });

        $this->assertEquals(get_class($response), get_class($result), "Middleware should return response object");
        $this->assertTrue($result->nextCalled, "'next' was not called");        
    }

    /**
     * Test error and not-error cases without calling 'next' callback
     *
     * @dataProvider invokeProvider
     * @param int $statusCode
     */
    public function testInvokeNoNext($statusCode)
    {
        $isError = $statusCode >= 400;
        $router = $this->getRouter();
        $middleware = new ErrorPage($router);
        list($request, $response) = $this->getRequests($statusCode);

        $isError ?
            $this->expectSetError($router, $request, $response, $statusCode) :
            $this->notExpectSetError($router, $request, $response, $statusCode);

        $result = $middleware($request, $response);

        $this->assertEquals($response, $result, "Middleware should return response object");     
    }

    /**
     * Provide data for testing '__invoke' method
     *
     * @return array
     */
    public function invokeProvider()
    {
        return [
            [200],
            [300],
            [400],
            [404],
            [500],
            [503]
        ];
    }

    /**
     * Get requests for testing
     *
     * @param int $statusCode 
     * @return array
     */
    public function getRequests($statusCode = null)
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        if ($statusCode) {
            $response->method('getStatusCode')->will($this->returnValue($statusCode));            
        }

        return [$request, $response];
    }

    /**
     * @return Router
     */
    public function getRouter()
    {
        return $this->getMockBuilder(Router::class)->disableOriginalConstructor()->getMock();
    }

    /**
     * Expect for error
     *
     * @param Router $router
     * @param ServerRequestInterface $request 
     * @param ResponseInterface $response 
     * @param int $statusCode 
     */
    public function expectSetError($router, $request, $response, $statusCode)
    {
        $uri = $this->createMock(UriInterface::class);

        $uri->expects($this->once())->method('withPath')->with($this->equalTo("/$statusCode"))->will($this->returnSelf());
        $request->expects($this->once())->method('getUri')->will($this->returnValue($uri));
        $request->expects($this->once())->method('withUri')->with($this->equalTo($uri), $this->equalTo(true))->will($this->returnSelf());
        $router->expects($this->once())->method('run')->with($this->equalTo($request), $this->equalTo($response))->will($this->returnValue($response));
    }

    /**
     * Not expect for error
     *
     * @param Router $router
     * @param ServerRequestInterface $request 
     * @param ResponseInterface $response 
     * @param int $statusCode 
     */
    public function notExpectSetError($router, $request, $response, $statusCode)
    {
        $uri = $this->createMock(UriInterface::class);

        $uri->expects($this->never())->method('withPath');
        $request->expects($this->never())->method('getUri');
        $request->expects($this->never())->method('withUri');
        $router->expects($this->never())->method('run');
    }
}
