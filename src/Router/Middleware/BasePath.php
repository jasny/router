<?php

namespace Jasny\Router\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Set base path for request
 */
class BasePath
{    
    /**
     * Base path
     * @var string
     **/
    protected $basePath = '';

    /**
     * Set base path
     *
     * @param string $basePath
     */
    public function __construct($basePath)
    {
        if (!$basePath || !is_string($basePath) || $basePath === '/') {
            throw new \InvalidArgumentException("Base path must be a string with at list one url segment");
        }

        $this->basePath = $this->normalizePath($basePath);
    }

    /**
     * Get base path
     *
     * @return string
     */
    public function getBasePath()
    {
        return $this->basePath;
    }

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

        $uri = $request->getUri();
        $path = $this->normalizePath($uri->getPath());

        if (!$this->hasBasePath($path)) return $this->setError($response);
        if (!$next) return $response;

        $noBase = $this->getBaselessPath($path);
        $uri = $uri->withPath($noBase);        
        $request = $request->withUri($uri)->withAttribute('original_uri', $path);

        return call_user_func($next, $request, $response);
    }

    /**
     * Remove base path from given path
     *
     * @param string $path
     * @return string
     */
    protected function getBaselessPath($path)
    {
        $path = preg_replace('|^' . preg_quote($this->getBasePath()) . '|i', '', $path);

        return $path ?: '/';
    }

    /**
     * Normalize path
     *
     * @param string $path
     * @return string
     */
    protected function normalizePath($path)
    {
        return '/' . trim($path, '/');
    }

    /**
     * Check that path starts with base path
     *
     * @param string $path
     * @return boolean
     */
    protected function hasBasePath($path)
    {
        return preg_match('#^' . preg_quote($this->getBasePath()) . '(\/|$)#i', $path);
    }

    /**
     * Set error response
     *
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function setError($response)
    {
        $message = 'Not Found';

        $body = $response->getBody();        
        $body->rewind();
        $body->write($message);

        return $response->withStatus(404, $message)->withBody($body);
    }
}
