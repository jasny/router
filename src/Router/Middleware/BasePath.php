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
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        if (!is_callable($next)) {
            throw new \InvalidArgumentException("'next' should be a callback");            
        }

        $uri = $request->getUri();
        $path = $this->normalizePath($uri->getPath());

        if (!$this->hasBasePath($path)) return $this->setError($response);

        $noBase = $this->getBaselessPath($path);
        $noBaseUri = $uri->withPath($noBase);        
        $request = $request->withUri($noBaseUri)->withAttribute('original_uri', $uri);

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
        return substr($path, strlen($this->getBasePath())) ?: '/';
    }

    /**
     * Normalize path
     *
     * @param string $path
     * @return string
     */
    protected function normalizePath($path)
    {
        return '/' . ltrim($path, '/');
    }

    /**
     * Check that path starts with base path
     *
     * @param string $path
     * @return boolean
     */
    protected function hasBasePath($path)
    {
        return strpos($path . '/', $this->getBasePath() . '/') === 0;
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
