<?php

use Jasny\Router\Middleware\BasePath;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class BasePathTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test creating middleware with invalid parameter
     *
     * @dataProvider invalidConstructProvider
     */
    public function testInvalidConstruct($basePath)
    {
        $this->expectException(\InvalidArgumentException::class);

        $pathHandler = new BasePath($basePath);
    }

    /**
     * Provide data for testing invalid BasePath creation
     *
     * @return array
     */
    public function invalidConstructProvider()
    {
        return [
            [''],
            ['/'],
            [null],
            [false],
            [['test']],
            [(object)['test']],
            [12345]
        ];
    }

    /**
     * Test creating BasePath instance
     *
     * @dataProvider validConstructProvider
     * @param string $basePath
     */
    public function testValidConstruct($basePath, $validBasePath)
    {
        $pathHandler = new BasePath($basePath);

        $this->assertNotEmpty($pathHandler->getBasePath(), "Empty base path");
        $this->assertEquals($validBasePath, $pathHandler->getBasePath(), "Base path was not set correctly");
    }

    /**
     * Provide data for testing BasePath creation
     *
     * @return array
     */
    public function validConstructProvider()
    {
        return [
            ['/foo', '/foo'],
            ['/foo/', '/foo/'],
            ['foo/', '/foo/'],
            ['/foo/bar', '/foo/bar'],
            ['foo/bar', '/foo/bar'],
            ['/foo/bar/', '/foo/bar/'],
            ['/foo/bar-zet/', '/foo/bar-zet/']
        ];
    }

    /**
     * Test invoke with invalid 'next' param
     */
    public function testInvokeInvalidNext()
    {
        $middleware = new BasePath('/foo');
        list($request, $response) = $this->getRequests();

        $this->expectException(\InvalidArgumentException::class);

        $result = $middleware($request, $response, 'not_callable');
    }

    /**
     * Test case when given request path does not starts with given base path
     *
     * @dataProvider notFoundProvider
     * @param string $basePath
     * @param string $path 
     */
    public function testNotFound($basePath, $path)
    {
        $middleware = new BasePath($basePath);
        list($request, $response) = $this->getRequests();

        $this->expectRequestGetPath($request, $path);
        $this->expectNotFound($response);

        $result = $middleware($request, $response, function($response, $request) {
            $response->nextCalled = true;

            return $response;
        });

        $this->assertEquals(get_class($response), get_class($result), "Middleware should return response object");
        $this->assertFalse(isset($response->nextCalled), "'next' was called");
    }

    /**
     * Provide data for testing BasePath creation
     *
     * @return array
     */
    public function notFoundProvider()
    {
        return [
            ['/foo', '/bar'],
            ['/foo', '/bar/foo'],
            ['/foo/bar', '/zet/foo/bar'],
            ['/foo/bar', '/foo/bar-/teta'],
            ['/foo/bar', '/foo/bar-zet/teta'],
            ['/foo/bar', '/foo/ba'],
            ['/foo/bar', '/foo'],
            ['/f', '/foo'],
        ];
    }

    /**
     * Test correct case, when path contains base path
     *
     * @dataProvider foundProvider
     * @param string $basePath
     * @param string $path 
     * @param string $noBasePath
     */
    public function testFound($basePath, $path, $noBasePath)
    {
        $middleware = new BasePath($basePath);
        list($request, $response) = $this->getRequests();

        $this->expectRequestSetBasePath($request, $basePath, $path, $noBasePath);

        $result = $middleware($request, $response, function($request, $response) {
            $response->nextCalled = true;

            return $response;
        });

        $this->assertEquals(get_class($response), get_class($result), "Middleware should return response object");
        $this->assertTrue($response->nextCalled, "'next' was not called");
    }

    /**
     * Provide data for testing BasePath creation
     *
     * @return array
     */
    public function foundProvider()
    {
        return [
            ['/foo', '/foo', '/'],
            ['foo', '/foo', '/'],
            ['/foo', '/foo/bar', '/bar'],
            ['/foo/bar', '/foo/bar', '/'],
            ['foo/bar', '/foo/bar', '/'],
            ['/foo/bar', '/foo/bar/zet', '/zet'],
            ['/f', '/f/foo', '/foo'],
            ['f', '/f/foo', '/foo'],
        ];
    }

    /**
     * Get requests for testing
     *
     * @param string $path
     * @return array
     */
    public function getRequests($path = null)
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        return [$request, $response];
    }

    /**
     * Expect that request will return a path
     *
     * @param ServerRequestInterface $request
     * @param string $path
     */
    public function expectRequestGetPath(ServerRequestInterface $request, $path)
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('getPath')->will($this->returnValue($path));
        $request->expects($this->once())->method('getUri')->will($this->returnValue($uri));
    }

    /**
     * Expect for setting base path for request
     *
     * @param ServerRequestInterface $request
     * @param string $basePath
     * @param string $path 
     * @param string $noBasePath
     */
    public function expectRequestSetBasePath(ServerRequestInterface $request, $basePath, $path, $noBasePath)
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('getPath')->will($this->returnValue($path));
        $uri->expects($this->once())->method('withPath')->with($this->equalTo($noBasePath))->will($this->returnSelf());

        $request->expects($this->once())->method('getUri')->will($this->returnValue($uri));
        $request->expects($this->once())->method('withUri')->with($this->equalTo($uri))->will($this->returnSelf());
        $request->expects($this->once())->method('withAttribute')->with($this->equalTo('original_uri'), $this->equalTo($uri))->will($this->returnSelf());
    }

    /**
     * Expect for not found error
     *
     * @param ResponseInterface $response 
     */
    public function expectNotFound(ResponseInterface $response)
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->once())->method('rewind');
        $stream->expects($this->once())->method('write')->with($this->equalTo('Not Found'));

        $response->method('getBody')->will($this->returnValue($stream));
        $response->expects($this->once())->method('withBody')->with($this->equalTo($stream))->will($this->returnSelf());
        $response->expects($this->once())->method('withStatus')->with($this->equalTo(404), $this->equalTo('Not Found'))->will($this->returnSelf());
    }
}
