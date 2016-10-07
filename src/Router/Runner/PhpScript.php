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
        echo (string)$this->route->file;
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
        $file = ltrim($this->file, '/');

        if (!file_exists($file)) {
            trigger_error("Failed to route using '$this': File '$file' doesn't exist.", E_USER_WARNING);
            return false;
        }

        if ($this->file[0] === '~' || strpos($this->file, '..') !== false || strpos($this->file, ':') !== false) {
            trigger_error("Won't route using '$this': '~', '..' and ':' not allowed in filename.", E_USER_WARNING);
            return false;
        }
        
        return include $file;
    }
}
