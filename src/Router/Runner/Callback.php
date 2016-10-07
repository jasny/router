<?php

namespace Jasny\Router\Runner;

use Jasny\Router\Runner;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Description of Callback
 *
 * @author arnold
 */
class Callback extends Runner
{
    /**
     * Use function to handle request and response
     * 
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @return ResponseInterface|mixed
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {
        $callback = !empty($this->route->fn) ? $this->route->fn : null;

        if (!is_callable($callback)) {
            throw new \RuntimeException("'fn' property of route shoud be a callable");
        }

        return call_user_func($callback, $request, $response);
    }
}
