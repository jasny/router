<?php

use Jasny\Router\Route;
use Jasny\Router\Runner\Callback;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use Jasny\Router\TestHelpers;

/**
 * @covers Jasny\Router\Runner\Callback
 */
class CallbackTest extends PHPUnit_Framework_TestCase
{
    use TestHelpers;
    
    /**
     * Test creating Callback runner
     */
    public function testCallback()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $finalResponse = $this->createMock(ResponseInterface::class);
        $finalResponse->foo = 10;
        
        $route = $this->createMock(Route::class);
        $route->fn = $this->createCallbackMock($this->once(), [$request, $response], $finalResponse);
        
        $request->expects($this->once())->method('getAttribute')->with('route')->willReturn($route);
            
        $runner = new Callback($route);
        $result = $runner($request, $response);
        
        $this->assertSame($finalResponse, $result);
    }
}
