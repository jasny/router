<?php

namespace Jasny\Router\Runner;

use Jasny\Router\Runner;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Description of Controller
 *
 * @author arnold
 */
class Controller extends Runner
{
    /**
     * Route to a file
     * 
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @return ResponseInterface|mixed
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {

    }
}
