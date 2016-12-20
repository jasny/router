<?php

namespace Jasny\Router\Helper;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Method for giving a 404 not found response
 */
trait NotFound
{
    /**
     * Return with a 404 not found response
     * 
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function notFound(ServerRequestInterface $request, ResponseInterface $response)
    {
        $finalResponse = $response
            ->withProtocolVersion($request->getProtocolVersion())
            ->withStatus(404)
            ->withHeader('Content-Type', 'text/plain');
        
        $finalResponse->getBody()->write("Not found");
        
        return $finalResponse;
    }
}
