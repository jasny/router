<?php

namespace Jasny\Router\Middleware;

use Jasny\Router\Middleware\BasePath;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

use Jasny\Router\TestHelpers;

/**
 * @covers Jasny\Router\Middleware\BasePath
 */
class BasePathTest extends \PHPUnit_Framework_TestCase
{
    use TestHelpers;
    
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
     * Test creating middleware with invalid parameter
     *
     * @dataProvider invalidConstructProvider
     * @expectedException InvalidArgumentException
     */
    public function testInvalidConstruct($basePath)
    {
        new BasePath($basePath);
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
     * Test invoke with invalid 'next' param
     * 
     * @expectedException InvalidArgumentException
     */
    public function testInvokeInvalidNext()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $middleware = new BasePath('/foo');

        $middleware($request, $response, 'not_callable');
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
     * Test case when given request path does not starts with given base path
     * @dataProvider notFoundProvider
     * 
     * @param string $basePath
     * @param string $path 
     */
    public function testNotFound($basePath, $path)
    {
        list($request, $response, $finalResponse) = $this->mockNotFound();

        $this->expectRequestGetPath($request, $path);

        $middleware = new BasePath($basePath);
        $next = $this->createCallbackMock($this->never());
        
        $result = $middleware($request, $response, $next);

        $this->assertSame($finalResponse, $result);
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
     * Test correct case, when path contains base path
     * @dataProvider foundProvider
     * 
     * @param string $basePath
     * @param string $path 
     * @param string $noBasePath
     */
    public function testFound($basePath, $path, $noBasePath)
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $finalRespose = $this->createMock(ResponseInterface::class);

        $middleware = new BasePath($basePath);

        $this->expectRequestSetBasePath($request, $basePath, $path, $noBasePath);

        $next = $this->createCallbackMock($this->once(), [$request, $response], $finalRespose);
        
        $result = $middleware($request, $response, $next);

        $this->assertSame($finalRespose, $result);
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
}
