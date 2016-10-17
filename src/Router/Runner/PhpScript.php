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

        if (!file_exists($file)) {
            throw new \RuntimeException("Failed to route using '$file': File '$file' doesn't exist.");
        }

        if ($file[0] === '~' || strpos($file, '..') !== false) {
            throw new \RuntimeException("Won't route using '$file': '~', '..' are not allowed in filename.");
        }
        
        $result = include $file;

        return $result === true ? $response : $result;
    }
}
