<?php

use Jasny\Router\Route;
use Jasny\Router\Runner\Controller;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ControllerTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test creating Controller runner
     *
     * @dataProvider phpScriptProvider
     * @param Route $route 
     * @param boolean $positive
     */
    public function testPhpScript($route, $positive)
    {   
        $runner = new Controller($route);
        $this->assertEquals($route, $runner->getRoute(), "Route was not set correctly");

        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        if (!$positive) $this->expectException(\RuntimeException::class);
        $result = $runner->run($request, $response);

        if (!$positive) {
            if (isset($route->path)) unlink($route->path);            
            return;
        }

        $this->assertEquals($request, $result['request'], "Request object was not passed correctly to result");
        $this->assertEquals($response, $result['response'], "Response object was not passed correctly to result");

        unlink($route->path);
    }

    /**
     * Provide data fpr testing 'create' method
     */
    public function phpScriptProvider()
    {
        $data = [];
        foreach (['noInvoke', 'withInvoke'] as $type) {
            list($class, $path) = $this->createTmpScript($type);
            $data[$type] = compact('class', 'path');
        }

        return [
            [Route::create(['test' => 'test']), false],
            [Route::create(['fn' => 'testFunction', 'value' => 'test']), false],
            [Route::create(['controller' => 'TestController', 'value' => 'test']), false],
            [Route::create(['controller' => '', 'value' => 'test']), false],
            [Route::create(['controller' => $data['noInvoke']['class'], 'path' => $data['noInvoke']['path']]), false],
            [Route::create(['controller' => $data['withInvoke']['class'], 'path' => $data['withInvoke']['path']]), true],
        ];
    }

    /**
     * Create single tmp script file for testing
     *
     * @param string $type ('returnTrue', 'returnNotTrue')
     * @return string $path
     */
    public function createTmpScript($type)
    {
        $dir = rtrim(sys_get_temp_dir(), '/');        

        do {
            $name = $this->getRandomString() . '-test-script.php';
            $path = $dir . '/' . $name;

            if (!file_exists($path)) break;
        } while (true);

        if ($type === 'noInvoke') {
            $class = 'RunnerTestConrtollerInvalid';
            $content =
<<<CONTENT
<?php

class $class {    
    public \$route = null;

    public function __construct(\$route) 
    {
        \$this->route = \$route;    
    }
}
CONTENT;
        } else {
            $class = 'RunnerTestConrtoller';
            $content = 
<<<CONTENT
<?php

class $class {    
    public \$route = null;
    
    public function __construct(\$route) 
    {
        \$this->route = \$route;    
    }
    
    public function __invoke(Psr\Http\Message\RequestInterface \$request, Psr\Http\Message\ResponseInterface \$response)
    {
        return ['request' => \$request, 'response' => \$response];
    }
}
CONTENT;
        }

        $bytes = file_put_contents($path, $content);
        $this->assertTrue((int)$bytes > 0);

        require_once $path;

        return [$class, $path];
    }

    /**
     * Get random string of given length (no more then length of md5 hash)
     *
     * @param int $length
     * @return string
     */
    public function getRandomString($length = 10)
    {        
        return substr(md5(microtime(true) * mt_rand()), 0, $length);
    }
}
