<?php

use Jasny\Router\Runner;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use Jasny\TestHelper;

/**
 * @covers Jasny\Router\Runner
 */
class RunnerTest extends PHPUnit_Framework_TestCase
{
    use TestHelper;
    
    /**
     * Test runner __invoke method
     */
    public function testInvoke()
    {
        $runner = $this->getMockBuilder(Runner::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        
        $request = $this->createMock(ServerRequestInterface::class);
        
        $response = $this->createMock(ResponseInterface::class);
        $runResponse = $this->createMock(ResponseInterface::class);
        $finalResponse = $this->createMock(ResponseInterface::class);

        $runner->expects($this->once())->method('run')
            ->with($request, $response)
            ->willReturn($runResponse);
        
        $next = $this->createCallbackMock($this->once(), [$request, $runResponse], $finalResponse);
        
        $runner($request, $response, $next);
    }
}
