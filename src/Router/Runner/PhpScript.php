<?php

namespace Jasny\Router\Runner;

use Jasny\Router\Runner;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Route to a PHP script
 */
class PhpScript extends Runner
{        
    /**
     * Return route file path
     * 
     * @return string
     */
    public function __toString()
    {
        return (string)$this->route->file;
    }    
    
    /**
     * Route to a file
     * 
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @return ResponseInterface|mixed
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {
        $file = !empty($this->route->file) ? ltrim($this->route->file, '/') : '';

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
