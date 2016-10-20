<?php

namespace Jasny\Router\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Handle error in following middlewares/app actions
 */
class ErrorHandler
{    
    /**
     * Run middleware action
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callback               $next
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next = null)
    {
        if ($next && !is_callable($next)) {
            throw new \InvalidArgumentException("'next' should be a callback");            
        }

        $error = false;

        try {
            $response = $next ? call_user_func($next, $request, $response) : $response;
        } catch(\Throwable $e) {
            $error = true;
        } catch(\Exception $e) { #This block can be removed when migrating to PHP7, because Throwable represents both Exception and Error
            $error = true;
        }

        return $error ? $this->handleError($response) : $response;
    }

    /**
     * Handle caught error
     *
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function handleError($response)
    {
        $body = $response->getBody();        
        $body->rewind();
        $body->write('Unexpected error');

        return $response->withStatus(500, 'Internal Server Error')->withBody($body);
    }
}
