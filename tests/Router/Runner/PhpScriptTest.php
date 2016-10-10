<?php

use Jasny\Router\Route;
use Jasny\Router\Runner\PhpScript;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class PhpScriptTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test creating PhpScript runner
     *
     * @dataProvider phpScriptProvider
     * @param Route $route 
     * @param boolean $positive
     */
    public function testPhpScript($route, $positive)
    {   
        $runner = new PhpScript($route);
        $this->assertEquals($route, $runner->getRoute(), "Route was not set correctly");

        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        if (!$positive) $this->expectException(\RuntimeException::class);
        $result = $runner->run($request, $response);

        if (!$positive) return;

        $this->assertEquals($runner->getRoute()->file, (string)$runner);

        if ($route->type === 'returnTrue') {
            $this->assertEquals($response, $result, "Request object was not returned as result");            
        } else {
            $this->assertEquals($request, $result['request'], "Request object was not passed correctly to result");
            $this->assertEquals($response, $result['response'], "Response object was not passed correctly to result");
        }

        unlink($route->file);
    }

    /**
     * Provide data fpr testing 'create' method
     */
    public function phpScriptProvider()
    {
        return [
            [Route::create(['test' => 'test']), false],
            [Route::create(['fn' => 'testFunction', 'value' => 'test']), false],
            [Route::create(['controller' => 'TestController', 'value' => 'test']), false],
            [Route::create(['file' => '', 'value' => 'test']), false],
            [Route::create(['file' => 'some_file.php', 'value' => 'test']), false],
            [Route::create(['file' => '../' . basename(getcwd()), 'value' => 'test']), false],
            [Route::create(['file' => $this->createTmpScript('returnTrue'), 'type' => 'returnTrue']), true],
            [Route::create(['file' => $this->createTmpScript('returnNotTrue'), 'type' => 'returnNotTrue']), true]
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

        $content = $type === 'returnTrue' ? "<?php\n return true;" : "<?php\n return ['request' => \$request, 'response' => \$response];";
        $bytes = file_put_contents($path, $content);

        $this->assertTrue((int)$bytes > 0);

        return $path;
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
