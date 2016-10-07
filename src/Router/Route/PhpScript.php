<?php

namespace Jasny\Router\Route;

use Jasny\Router\Route;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Route to a PHP script
 */
class PhpScript extends Route
{
    /**
     * Route key
     * @var string
     */
    protected $key;
    
    /**
     * Script path
     * @var string
     */
    public $file;
    
    
    /**
     * Class constructor
     * 
     * @param string $file
     * @param string $values
     */
    public function __construct($key, $file, $values)
    {
        parent::__construct($values);
        
        $this->key = $key;
        $this->file = $file;
    }
    
    /**
     * Return route key
     * 
     * @return string
     */
    public function __toString()
    {
        echo (string)$this->key;
    }
    
    
    /**
     * Route to a file
     * 
     * @param object $route
     * @return Response|mixed
     */
    protected function execute()
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
