<?php

use Jasny\Router\Route;
use Jasny\Router\Runner\RunnerFactory;
use Jasny\Router\Runner\Controller;
use Jasny\Router\Runner\Callback;
use Jasny\Router\Runner\PhpScript;

class RunnerFactoryTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test creating Runner object using factory
     *
     * @dataProvider createProvider
     * @param Route $route 
     * @param string $class          Runner class to use
     * @param boolean $positive 
     */
    public function testCreate($route, $class, $positive)
    {   
        if (!$positive) $this->expectException(\InvalidArgumentException::class);

        $factory = new RunnerFactory();
        $runner = $factory($route);

        $this->assertInstanceOf($class, $runner, "Runner object has invalid class");
    }

    /**
     * Provide data fpr testing 'create' method
     */
    public function createProvider()
    {
        return [
            [Route::create(['controller' => 'TestController', 'value' => 'test']), Controller::class, true],
            [Route::create(['fn' => 'testFunction', 'value' => 'test']), Callback::class, true],
            [Route::create(['file' => 'some_file.php', 'value' => 'test']), PhpScript::class, true],
            [Route::create(['test' => 'test']), '', false],
        ];
    }
}
