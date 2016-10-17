<?php

use Jasny\Router\Route;
use Jasny\Router\Runner;
use Jasny\Router\Runner\Controller;
use Jasny\Router\Runner\Callback;
use Jasny\Router\Runner\PhpScript;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class RunnerTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test runner __invoke method
     */
    public function testInvoke()
    {
        $runner = $this->getMockBuilder('Jasny\Router\Runner')->disableOriginalConstructor()->getMockForAbstractClass();
        $queries = [
            'request' => $this->createMock(ServerRequestInterface::class),
            'response' => $this->createMock(ResponseInterface::class)
        ];

        #Test that 'run' receives correct arguments inside '__invoke'
        $runner->method('run')->will($this->returnCallback(function($arg1, $arg2) {
            return ['request' => $arg1, 'response' => $arg2];
        }));

        $result = $runner($queries['request'], $queries['response']);
        $this->assertEquals($result['request'], $queries['request'], "Request was not returned correctly from 'run'");
        $this->assertEquals($result['response'], $queries['response'], "Response was not returned correctly from 'run'");

        #The same test with calling 'next' callback
        $result = $runner($queries['request'], $queries['response'], function($request, $prevResponse) use ($queries) {
            $this->assertEquals($request, $queries['request'], "Request is not correct in 'next'");
            $this->assertEquals($prevResponse['request'], $queries['request'], "Prev response was not passed correctly to 'next'");
            $this->assertEquals($prevResponse['response'], $queries['response'], "Prev response was not passed correctly to 'next'");

            return $queries + ['next_called' => true];
        });

        $this->assertTrue($result['next_called'], "'Next' callback was not called");
        $this->assertEquals($result['request'], $queries['request'], "Request was not returned correctly from 'run' with 'next'");
        $this->assertEquals($result['response'], $queries['response'], "Request was not returned correctly from 'run' with 'next'");
    }
}
