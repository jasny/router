<?php

namespace Jasny\Router;

use Jasny\Router\Route;
use Jasny\Router\Runner;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

/**
 * @covers Jasny\Router\Runner\PhpScript
 */
class PhpScriptTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var vfsStreamDirectory
     */
    protected $root;
    
    public function setUp()
    {
        $this->root = vfsStream::setup('root');
        $this->root->addChild(vfsStream::newFile('true.php')->setContent('<?php ?>'));
        $this->root->addChild(vfsStream::newFile('foo.php')->setContent('<?php return "foo"; ?>'));
    }

    public function testInvoke()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $finalResponse = $this->createMock(ResponseInterface::class);

        $route = $this->createMock(Route::class);
        $route->file = vfsStream::url('root/foo.php');
        
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
        
        $runner = $this->getMockBuilder(Runner\PhpScript::class)->setMethods(['includeScript'])->getMock();
        $runner->expects($this->once())->method('includeScript')
            ->with(vfsStream::url('root/foo.php'), $request, $response)
            ->willReturn($finalResponse);

        $result = $runner($request, $response);
        
        $this->assertSame($finalResponse, $result);
    }
    
    public function phpScriptProvider()
    {
        $routeTrue = $this->createMock(Route::class);
        $routeTrue->file = vfsStream::url('root/true.php');
        
        $routeFoo = $this->createMock(Route::class);
        $routeFoo->file = vfsStream::url('root/foo.php');
        
        return [
            [$routeTrue, 1],
            [$routeFoo, 'foo']
        ];
    }
    
    /**
     * @dataProvider phpScriptProvider
     * 
     * @param Route $route
     * @param mixed $expected
     */
    public function testInvokeIncludeScript($route, $expected)
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request->expects($this->once())->method('getAttribute')->with($this->equalTo('route'))->will($this->returnValue($route));

        $runner = new Runner\PhpScript($route);
        
        if ($expected === 1) {
            $expected = $response;
        }
        
        $result = $runner->run($request, $response);
        
        $this->assertSame($expected, $result);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Failed to route using 'vfs://root/bar.php': File doesn't exist
     */
    public function testInvokeWithNonExistingFile()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $route = $this->createMock(Route::class);
        $route->file = vfsStream::url('root/bar.php');
        
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
        
        $runner = $this->getMockBuilder(Runner\PhpScript::class)->setMethods(['includeScript'])->getMock();
        $runner->expects($this->never())->method('includeScript');

        $runner($request, $response);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Won't route to 'vfs://root/../bar.php': '~', '..' are not allowed in filename
     */
    public function testInvokeWithIlligalFilename()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $route = $this->createMock(Route::class);
        $route->file = vfsStream::url('root/../bar.php');
        
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
        
        $runner = $this->getMockBuilder(Runner\PhpScript::class)->setMethods(['includeScript'])->getMock();
        $runner->expects($this->never())->method('includeScript');

        $runner($request, $response);
    }
    
}
