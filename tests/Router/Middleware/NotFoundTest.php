<?php

use Jasny\Router\Routes;
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
     * @param boolean $positive 
     */
    public function testConstruct($notFound, $notAllowed, $positive)
    {   
        if (!$positive) $this->expectException(\InvalidArgumentException::class);

        $middleware = new NotFound($this->getRoutes(), $notFound, $notAllowed);

        if ($positive) $this->skipTest();
    }

    /**
     * Provide data for testing '__contruct'
     */
    public function constructProvider()
    {
        return [
            [null, 405, false],
            [true, true, false],
            [99, null, false],
            [600, null, false],
            [404, 99, false],
            [404, 600, false],
            [200, 405, true],
            [404, 200, true]
        ];
    }

    /**
     * Test invoke with invalid 'next' param
     */
    public function testInvokeInvalidNext()
    {   
        $middleware = new NotFound($this->getRoutes(), 404, 405);
        list($request, $response) = $this->getRequests();

        $this->expectException(\InvalidArgumentException::class);

        $result = $middleware($request, $response, 'not_callable');
    }

    /**
     * Test that 'next' callback is invoked when route is found
     *
     * @dataProvider invokeProvider
     * @param callback|int $notFound 
     * @param callback|int $notAllowed 
     * @param callback $next
     */
    public function testInvokeFound($notFound, $notAllowed, $next)
    {
        if (!$next) return $this->skipTest();

        list($request, $response) = $this->getRequests();
        $routes = $this->getRoutes();
        $middleware = new NotFound($routes, $notFound, $notAllowed);

        $this->expectRoute($routes, $request, 'found');
        $this->notExpectSimpleError($response);

        $result = $middleware($request, $response, $next);

        $this->assertEquals(get_class($response), get_class($result), "Result must be an instance of 'ResponseInterface'");
        $this->assertTrue($result->nextCalled, "'next' was not called");
        $this->assertFalse(isset($result->notAllowedCalled), "'Not allowed' callback was called");
        $this->assertFalse(isset($result->notFoundCalled), "'Not found' callback was called");
    }

    /**
     * Test __invoke method in case of route is found with another method
     *
     * @dataProvider invokeProvider
     * @param callback|int $notFound 
     * @param callback|int $notAllowed 
     * @param callback $next
     */
    public function testInvokeNotAllowed($notFound, $notAllowed, $next)
    {
        if (!$notAllowed) return $this->skipTest();

        list($request, $response) = $this->getRequests();
        $routes = $this->getRoutes();
        $middleware = new NotFound($routes, $notFound, $notAllowed);

        $this->expectRoute($routes, $request, 'notAllowed');
        if (is_numeric($notAllowed)) {
            $this->expectSimpleError($response, $notAllowed);
        }

        $result = $middleware($request, $response, $next);

        $this->assertEquals(get_class($response), get_class($result), "Result must be an instance of 'ResponseInterface'");
        $this->assertFalse(isset($result->nextCalled), "'next' was called");
        
        if (is_callable($notAllowed)) {
            $this->assertTrue($result->notAllowedCalled, "'Not allowed' callback was not called");
        }
    }

    /**
     * Test __invoke method in case of route not found at all
     *
     * @dataProvider invokeProvider
     * @param callback|int $notFound 
     * @param callback|int $notAllowed 
     * @param callback $next
     */
    public function testInvokeNotFound($notFound, $notAllowed, $next)
    {
        list($request, $response) = $this->getRequests();
        $routes = $this->getRoutes();
        $middleware = new NotFound($routes, $notFound, $notAllowed);

        $case = $notAllowed ? 'notFoundTwice' : 'notFoundOnce';
        $this->expectRoute($routes, $request, $case);

        if (is_numeric($notFound)) {
            $this->expectSimpleError($response, $notFound);
        }

        $result = $middleware($request, $response, $next);

        $this->assertEquals(get_class($response), get_class($result), "Result must be an instance of 'ResponseInterface'");
        $this->assertFalse(isset($result->nextCalled), "'next' was called");
        
        if (is_callable($notAllowed)) {
            $this->assertFalse(isset($result->notAllowedCalled), "'Not allowed' callback was called");
        }
        if (is_callable($notFound)) {
            $this->assertTrue($result->notFoundCalled, "'Not found' callback was not called");
        }
    }

    /**
     * Set expectations on finding route
     *
     * @param Routes $routes
     * @param ServerRequestInterface $request
     * @param string $case 
     */
    public function expectRoute($routes, $request, $case)
    {
        if ($case === 'found' || $case === 'notFoundOnce') {
            $found = $case === 'found';

            $routes->expects($this->once())->method('hasRoute')
                ->with($this->equalTo($request))->will($this->returnValue($found));            
        } elseif ($case === 'notAllowed' || $case === 'notFoundTwice') {
            $routes->expects($this->exactly(2))->method('hasRoute')
                ->withConsecutive(
                    [$this->equalTo($request)],
                    [$this->equalTo($request), $this->equalTo(false)]
                )->will($this->returnCallback(function($request, $searchMethod = true) use ($case) {
                    return $case === 'notFoundTwice' ? false : !$searchMethod;
                }));
        }
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
     * @param int $statusCode 
     */
    public function expectSimpleError(ResponseInterface $response, $statusCode)
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->once())->method('rewind');
        $stream->expects($this->once())->method('write')->with($this->equalTo('Not Found'));

        $response->method('getBody')->will($this->returnValue($stream));
        $response->expects($this->once())->method('withBody')->with($this->equalTo($stream))->will($this->returnSelf());
        $response->expects($this->once())->method('withStatus')->with($this->equalTo($statusCode), $this->equalTo('Not Found'))->will($this->returnSelf());
    }

    /**
     * Expect that there would be no simple error response
     *
     * @param ResponseInterface $response
     */
    public function notExpectSimpleError(ResponseInterface $response)
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->never())->method('rewind');
        $stream->expects($this->never())->method('write');

        $response->expects($this->never())->method('getBody');
        $response->expects($this->never())->method('withBody');
        $response->expects($this->never())->method('withStatus');   
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
     * Get routes array
     *
     * @return Routes
     */
    public function getRoutes()
    {
        return $this->getMockBuilder(Routes::class)->disableOriginalConstructor()->getMock();        
    }

    /**
     * Skip the test pass
     */
    public function skipTest()
    {
        return $this->assertTrue(true);
    }
}
