<?php

namespace Jasny\Router\Routes;

use ArrayObject;
use Jasny\Router\UrlParsing;
use Jasny\Router\Routes;
use Jasny\Router\Route;
use Jasny\Router\Routes\RouteBinding;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Match URL against a shell wildcard pattern. 
 */
class Glob extends ArrayObject implements Routes
{
    use UrlParsing;
    use RouteBinding;
    
    /**
     * Class constructor
     * 
     * @param Routes[]|array|\Traversable $input
     * @param int                         $flags
     */
    public function __construct($input = [], $flags = 0)
    {
        $routes = $this->createRoutes($input);
        parent::__construct($routes, $flags);
    }
    
    /**
     * Create a route from an assisiative array or stdClass object
     * 
     * @param Route|\stdClass|array $value
     * @return Route
     */
    protected function createRoute($value)
    {
        if ($value instanceof Route) {
            return $value;
        }
        
        if (is_array($value)) {
            $value = (object)$value;
        }
        
        if (!$value instanceof \stdClass) {
            throw new \InvalidArgumentException("Unable to create a Route from value " . var_export($value, true));
        }
        
        return new Route($value);
    }
    
    /**
     * Create routes from input
     * 
     * @param Route[]|array|\Traversable $input
     * @return type
     */
    protected function createRoutes($input)
    {
        if ($input instanceof \Traversable) {
            $input = iterator_to_array($input, true);
        }

        return array_map([$this, 'createRoute'], $input);
    }
    
    /**
     * {@inheritdoc}
     */
    public function append($route)
    {
        throw new \BadMethodCallException("Unable to append a Route without a pattern");
    }

    /**
     * Replace all the routes
     * 
     * @param Route[]|array|\Traversable $input
     * @return array  the old routes
     */
    public function exchangeArray($input)
    {
        $routes = $this->createRoutes($input);
        return parent::exchangeArray($routes);
    }

    /**
     * Add a route
     * 
     * @param string                $pattern
     * @param Route|\stdClass|array $value
     */
    public function offsetSet($pattern, $value)
    {
        if (empty($pattern)) {
            throw new \BadMethodCallException("Unable to append a Route without a pattern");
        }
        
        $route = $this->createRoute($value);
        parent::offsetSet($pattern, $route);
    }

    
    /**
     * Match url against wildcard pattern.
     * 
     * @param string $pattern
     * @param string $url
     * @return boolean
     */
    public function fnmatch($pattern, $url)
    {
        $quoted = preg_quote($pattern, '~');

        $step1 = strtr($quoted, ['\?' => '[^/]', '\*' => '[^/]*', '/\*\*' => '(?:/.*)?', '#' => '\d+', '\[' => '[',
            '\]' => ']', '\-' => '-', '\{' => '{', '\}' => '}']);

        $step2 = preg_replace_callback('~{[^}]+}~', function ($part) {
            return '(?:' . substr(strtr($part[0], ',', '|'), 1, -1) . ')';
        }, $step1);

        $regex = rawurldecode($step2);
        return (boolean)preg_match("~^{$regex}$~", $url);
    }
    
    protected function splitRoutePattern($pattern)
    {
        if (strpos($pattern, ' ') !== false && preg_match_all('/\s+\+(\w+)\b|\s+\-(\w+)\b/', $pattern, $matches)) {
            list($path) = preg_split('/\s+/', $pattern, 2);
            $inc = isset($matches[1]) ? array_filter($matches[1]) : [];
            $excl = isset($matches[2]) ? array_filter($matches[2]) : [];
        } else {
            $path = $pattern;
            $inc = [];
            $excl = [];
        }
        
        return [$path, $inc, $excl];
    }
    
    /**
     * Find a matching route
     * 
     * @param UriInterface $url
     * @param string       $method
     * @return string
     */
    protected function findRoute(UriInterface $url, $method = null)
    {
        $urlPath = $this->cleanUrl($url->getPath());
        $ret = null;
        
        foreach ($this as $pattern => $route) {
            list($path, $inc, $excl) = $this->splitRoutePattern($pattern);
            if ($path !== '/') $path = rtrim($path, '/');
            
            if ($this->fnmatch($path, $urlPath)) {
                if (!$method || ((empty($inc) || in_array($method, $inc)) && !in_array($method, $excl))) {
                    $ret = $route;
                    break;
                }
            }
        }

        return $ret;
    }
    
    
    /**
     * Check if a route for the URL exists
     * 
     * @param ServerRequestInterface $request
     * @return boolean
     */
    public function hasRoute(ServerRequestInterface $request, $withMethod = true)
    {
        $route = $this->findRoute($request->getUri(), $withMethod ? $request->getMethod() : null);
        return isset($route);
    }
    
    /**
     * Get route for the request
     * 
     * @param ServerRequestInterface $request
     * @return Route
     */
    public function getRoute(ServerRequestInterface $request)
    {
        $url = $request->getUri();
        $route = $this->findRoute($url, $request->getMethod());
        
        if ($route) {
            $route = $this->bind($route, $request, $this->splitUrl($url->getPath()));
        }
        
        return $route;
    }
}
