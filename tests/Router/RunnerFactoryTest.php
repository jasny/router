<?php

namespace Jasny\Router;

use Jasny\Router\RunnerFactory;
use Jasny\Router\Route;
use Jasny\Router\Runner;

class RunnerFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Provide data fpr testing 'create' method
     */
    public function createProvider()
    {
        $routeController = $this->createMock(Route::class);
        $routeController->controller = 'foo-bar';
        
        $routeCallback = $this->createMock(Route::class);
        $routeCallback->fn = function() {};
        
        $routePhpScript = $this->createMock(Route::class);
        $routePhpScript->file = 'some_file.php';
        
        return [
            [$routeController, Runner\Controller::class],
            [$routeCallback, Runner\Callback::class],
            [$routePhpScript, Runner\PhpScript::class],
        ];
    }
    
    /**
     * Test creating Runner object using factory
     * @dataProvider createProvider
     * 
     * @param Route  $route 
     * @param string $class  Runner class to use
     */
    public function testCreate($route, $class)
    {   
        $factory = new RunnerFactory();
        $runner = $factory($route);

        $this->assertInstanceOf($class, $runner, "Runner object has invalid class");
    }
    
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Route has neither 'controller', 'fn' or 'file' defined
     */
    public function testCreatWithInvalideRoute()
    {
        $route = $this->createMock(Route::class);
        
        $factory = new RunnerFactory();
        $factory($route);
    }
}
