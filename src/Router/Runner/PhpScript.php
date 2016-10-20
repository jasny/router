<?php

namespace Jasny\Router\Runner;

use Jasny\Router\Runner;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Route to a PHP script
 */
class PhpScript extends Runner
{
    /**
     * Include a file 
     * @param type $file
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     */
    protected function includeScript($file, ServerRequestInterface $request, ResponseInterface $response)
    {
        return include $file;
    }
    
    /**
     * Route to a file
     * 
     * @param ServerRequestInterface  $request
     * @param ResponseInterface       $response
     * @return ResponseInterface|mixed
     */
    public function run(ServerRequestInterface $request, ResponseInterface $response)
    {
        $route = $request->getAttribute('route');        
        $file = !empty($route->file) ? ltrim($route->file, '/') : '';

        if ($file[0] === '~' || strpos($file, '..') !== false) {
            throw new \RuntimeException("Won't route to '$file': '~', '..' are not allowed in filename");
        }
        
        if (!file_exists($file)) {
            throw new \RuntimeException("Failed to route using '$file': File doesn't exist");
        }

        $result = $this->includeScript($file, $request, $response);

        return $result === true || $result === 1 ? $response : $result;
    }
}
