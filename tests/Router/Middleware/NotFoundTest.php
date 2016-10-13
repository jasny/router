<?php

use Jasny\Router\Middleware\NotFound;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class NotFoundTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test creating object with false parameters
     *
     * @dataProvider constructProvider
     * @param string $notFound 
     * @param string $notAllowed
     */
    public function testConstruct($notFound, $notAllowed)
    {   
        $this->expectException(\InvalidArgumentException::class);

        $middleware = new NotFound([], $notFound, $notAllowed);
    }

    /**
     * Provide data for testing '__contruct'
     */
    public function constructProvider()
    {
        return [
            [404, 406],
            [200, 405],
            [null, 405],
            [true, true]
        ];
    }

    /**
     * Test invoke with invalid 'next' param
     */
    public function testInvokeInvalidNext()
    {
        $middleware = new NotFound([], 404, 405);
        list($request, $response) = $this->getRequests('/foo', 'POST');

        $this->expectException(\InvalidArgumentException::class);
        
        $result = $middleware($request, $response, 'not_callable');
    }

    /**
     * Test runner __invoke method
     *
     * @dataProvider invokeProvider
     * @param callback|int $notFound 
     * @param callback|int $notAllowed 
     * @param callback $next
     */
    public function testInvokeNoNext($notFound, $notAllowed, $next)
    {
        $routes = $this->getRoutes();
        $middleware = new NotFound($routes, $notFound, $notAllowed);
        list($request, $response) = $this->getRequests('/foo', 'POST');

        if (is_numeric($notAllowed)) {
            $this->expectSimpleDeny($response, 405, 'Method Not Allowed');
        } elseif (!$notAllowed && is_numeric($notFound)) {
            $this->expectSimpleDeny($response, 404, 'Not Found');
        }

        $result = $middleware($request, $response, $next);

        $this->assertEquals(get_class($response), get_class($result), "Result must be an instance of 'ResponseInterface'");
        $this->assertTrue(!isset($result->nextCalled), "'next' was called");
        
        if (is_callable($notAllowed)) {
            $this->assertTrue($result->notAllowedCalled, "'Not allowed' callback was not called");
        } elseif (!$notAllowed && is_callable($notFound)) {
            $this->assertTrue($result->notFoundCalled, "'Not found' callback was not called");
        }
    }

    /**
     * Text that 'next' callback is invoked when route is found
     *
     * @dataProvider invokeProvider
     * @param callback|int $notFound 
     * @param callback|int $notAllowed 
     * @param callback $next
     */
    public function testInvokeNext($notFound, $notAllowed, $next)
    {
        if (!$next) return $this->skipTest();

        $routes = $this->getRoutes();
        $middleware = new NotFound($routes, $notFound, $notAllowed);
        list($request, $response) = $this->getRequests('/foo', 'GET');

        $result = $middleware($request, $response, $next);

        $this->assertEquals(get_class($response), get_class($result), "Result must be an instance of 'ResponseInterface'");
        $this->assertTrue($result->nextCalled, "'next' waas not called");
    }

    /**
     * Provide data for testing invoke method
     */
    public function invokeProvider()
    {
        $callbacks = [];
        foreach (['notFound', 'notAllowed', 'next'] as $type) {
            $var = $type . 'Called';

            $callbacks[$type] = function($request, $response) use ($var) {
                $response->$var = true;
                return $response;
            };
        }

        return [
            [404, 405, $callbacks['next']],
            [404, 405, null],
            [404, null, $callbacks['next']],
            [404, null, null],
            [$callbacks['notFound'], $callbacks['notAllowed'], $callbacks['next']],
            [$callbacks['notFound'], $callbacks['notAllowed'], null],
            [$callbacks['notFound'], null, $callbacks['next']],
            [$callbacks['notFound'], null, null]
        ];
    }

    /**
     * Expect that response is set to simple deny answer
     *
     * @param ResponseInterface $response
     * @param int $code 
     * @param string $reasonPhrase 
     */
    public function expectSimpleDeny(ResponseInterface $response, $code, $reasonPhrase)
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->once())->method('rewind');
        $stream->expects($this->once())->method('write')->with($this->equalTo($reasonPhrase));

        $response->method('getBody')->will($this->returnValue($stream));
        $response->expects($this->once())->method('withBody')->with($this->equalTo($stream))->will($this->returnSelf());
        $response->expects($this->once())->method('withStatus')->with($this->equalTo($code), $this->equalTo($reasonPhrase))->will($this->returnSelf());
    }

    /**
     * Get requests for testing
     *
     * @param string $uri 
     * @param string $method 
     * @return array
     */
    public function getRequests($uri, $method)
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $request->method('getUri')->will($this->returnValue($uri));
        $request->method('getMethod')->will($this->returnValue($method));

        return [$request, $response];
    }

    /**
     * Get routes array
     *
     * @return array
     */
    public function getRoutes()
    {
        return [
            '/' => ['controller' => 'test'],
            '/foo/bar' => ['controller' => 'test'],
            '/foo +GET' => ['controller' => 'test'],
            '/foo +OPTIONS' => ['controller' => 'test'],
            '/bar/foo/zet -POST' => ['controller' => 'test']
        ];
    }

    /**
     * Skip the test pass
     */
    public function skipTest()
    {
        return $this->assertTrue(true);
    }
}
