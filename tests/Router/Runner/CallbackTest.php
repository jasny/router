<?php

use Jasny\Router\Route;
use Jasny\Router\Runner\Callback;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class CallbackTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test creating Callback runner
     *
     * @dataProvider callbackProvider
     * @param Route $route 
     * @param boolean $positive
     */
    public function testCallback($route, $positive)
    {   
        $runner = new Callback($route);
        $this->assertEquals($route, $runner->getRoute(), "Route was not set correctly");

        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        if (!$positive) $this->expectException(\RuntimeException::class);
        $result = $runner->run($request, $response);

        if (!$positive) return;

        $this->assertEquals($request, $result['request'], "Request object was not passed correctly to result");
        $this->assertEquals($response, $result['response'], "Response object was not passed correctly to result");
    }


    /**
     * Provide data fpr testing 'create' method
     */
    public function callbackProvider()
    {
        $callback = function($request, $response) {
            return ['request' => $request, 'response' => $response];
        };

        return [
            [Route::create(['fn' => $callback, 'value' => 'test']), true],
            [Route::create(['fn' => [$this, 'getCallback'], 'value' => 'test']), true],
            [Route::create(['controller' => 'TestController', 'value' => 'test']), false],
            [Route::create(['file' => 'some_file.php', 'value' => 'test']), false],
            [Route::create(['test' => 'test']), false],
        ];
    }

    /**
     * Testable callback for creating Route
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @return array
     */
    public function getCallback($request, $response) 
    {
        return ['request' => $request, 'response' => $response];
    }
}
