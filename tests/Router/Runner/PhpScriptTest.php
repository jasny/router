<?php

namespace Jasny\Router\Runner;

use Jasny\Router\Route;
use Jasny\Router\Runner;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Jasny\TestHelper;
use Jasny\Router\MockResponse;

/**
 * @covers Jasny\Router\Runner\PhpScript
 * @covers Jasny\Router\Runner\Implementation
 * @covers Jasny\Router\Helper\NotFound
 */
class PhpScriptTest extends \PHPUnit_Framework_TestCase
{
    use TestHelper;
    use MockResponse;

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
    
    public function testInvokeWithNext()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $runResponse = $this->createMock(ResponseInterface::class);
        $finalResponse = $this->createMock(ResponseInterface::class);

        $route = $this->createMock(Route::class);
        $route->file = vfsStream::url('root/foo.php');
        
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
        
        $next = $this->createCallbackMock($this->once(), [$request, $runResponse], $finalResponse);
        
        $runner = $this->getMockBuilder(Runner\PhpScript::class)->setMethods(['includeScript'])->getMock();
        $runner->expects($this->once())->method('includeScript')
            ->with(vfsStream::url('root/foo.php'), $request, $response)
            ->willReturn($runResponse);

        $result = $runner($request, $response, $next);
        
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
        
        $request->expects($this->once())->method('getAttribute')->with($this->equalTo('route'))
            ->willReturn($route);

        $runner = new Runner\PhpScript($route);
        
        if ($expected === 1) {
            $expected = $response;
        }
        
        $result = $runner->run($request, $response);
        
        $this->assertSame($expected, $result);
    }

    public function invalidScriptProvider()
    {
        return [
            ['root/bar.php', "Failed to route using 'vfs://root/bar.php': File doesn't exist"],
            ['root/../bar.php', "Won't route to 'vfs://root/../bar.php': '~', '..' are not allowed in filename"]
        ];
    }
    
    /**
     * @dataProvider invalidScriptProvider
     * 
     * @param string $path
     * @param string $warning
     */
    public function testInvokeWithInvalidScript($path, $warning)
    {
        list($request, $response, $notFoundResponse) = $this->mockNotFound();

        $route = $this->createMock(Route::class);
        $route->file = vfsStream::url($path);
        
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
        
        $runner = $this->getMockBuilder(Runner\PhpScript::class)->setMethods(['includeScript'])->getMock();
        $runner->expects($this->never())->method('includeScript');

        $result = @$runner($request, $response);
        
        $this->assertLastError(E_USER_NOTICE, $warning);
        
        $this->assertSame($notFoundResponse, $result);
    }
}
