<?php

namespace Jasny\Router\Routes;

use ArrayObject;
use Jasny\Router\UrlParsing;
use Jasny\Router\Routes;
use Jasny\Router\Route;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Match URL against a shell wildcard pattern. 
 */
class Glob extends ArrayObject implements Routes
{
    use UrlParsing;
    
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
        
        return Route::create($value);
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
    
    /**
     * Find a matching route
     * 
     * @param string $url
     * @param string $method
     * @return string
     */
    protected function findRoute($url, $method = null)
    {
        $url = $this->cleanUrl($url);
        $ret = null;
        
        foreach ($this as $pattern => $route) {
            if (strpos($pattern, ' ') !== false && preg_match_all('/\s+\+(\w+)\b|\s+\-(\w+)\b/', $pattern, $matches)) {
                list($path) = preg_split('/\s+/', $pattern, 2);
                $inc = isset($matches[1]) ? array_filter($matches[1]) : [];
                $excl = isset($matches[2]) ? array_filter($matches[2]) : [];
            } else {
                $path = $pattern;
                $inc = [];
                $excl = [];
            }
            
            if ($path !== '/') $path = rtrim($path, '/');
            
            if ($this->fnmatch($path, $url)) {
                if (!$method || ((empty($inc) || in_array($method, $inc)) && !in_array($method, $excl))) {
                    $ret = $route;
                    break;
                }
            }
        }

        return $ret;
    }
    
    
    /**
     * Fill out the routes variables based on the url parts.
     * 
     * @param array|\stdClass $vars     Route variables
     * @param ServerRequestInterface   $request
     * @param array           $parts    URL parts
     * @return array
     */
    protected function bind($vars, ServerRequestInterface $request, array $parts)
    {
        $values = [];
        $type = is_array($vars) && array_keys($vars) === array_keys(array_keys($vars)) ? 'numeric' : 'assoc';

        foreach ($vars as $key => $var) {
            if (!isset($var)) continue;
            
            if (is_object($var) && !$var instanceof \stdClass) {
                $part = array($var);
            } elseif (!is_scalar($var)) {
                $part = array($this->bind($var, $request, $parts));
            } elseif ($var[0] === '$') {
                $options = array_map('trim', explode('|', $var));
                $part = $this->bindVar($type, $request, $parts, $options);
            } elseif ($var[0] === '~' && substr($var, -1) === '~') {
                $pieces = array_map('trim', explode('~', substr($var, 1, -1)));
                $bound = array_filter($this->bind($pieces, $request, $parts));
                $part = array(join('', $bound));
            } else {
                $part = array($var);
            }
            
            if ($type === 'assoc') {
                $values[$key] = $part[0];
            } else {
                $values = array_merge($values, $part);
            }
        }

        if ($vars instanceof Route) {
            $values = Route::create($values);
        } elseif (is_object($vars) && $type === 'assoc') {
            $values = (object)$values;
        }
        
        return $values;
    }
    
    /**
     * Bind variable
     * 
     * @param string                 $type     'assoc' or 'numeric'
     * @param ServerRequestInterface $request
     * @param array                  $parts
     * @param array                  $options
     * @return array
     */
    protected function bindVar($type, ServerRequestInterface $request, array $parts, array $options)
    {
        foreach ($options as $option) {
            $value = null;
            
            $bound = 
                $this->bindVarString($option, $value) ||
                $this->bindVarSuperGlobal($option, $request, $value) ||
                $this->bindVarRequestHeader($option, $request, $value) ||
                $this->bindVarMultipleUrlParts($option, $type, $parts, $value) ||
                $this->bindVarSingleUrlPart($option, $parts, $value);
            
            if ($bound && isset($value)) {
                return $value;
            }
        }
        
        return [null];
    }
    
    /**
     * Bind variable when option is a normal string
     * 
     * @param string $option
     * @param mixed  $value   OUTPUT
     * @return boolean
     */
    protected function bindVarString($option, &$value)
    {
        if ($option[0] !== '$') {
            $value = [$option];
            return true;
        }
        
        return false;
    }
     
    /**
     * Bind variable when option is a super global
     * 
     * @param string $option
     * @param mixed  $value   OUTPUT
     * @return boolean
     */
    protected function bindVarSuperGlobal($option, ServerRequestInterface $request, &$value)
    {
        if (preg_match('/^\$_(GET|POST|COOKIE)\[([^\[]*)\]$/i', $option, $matches)) {
            list(, $var, $key) = $matches;

            $var = strtolower($var);
            $data = null;

            if ($var === 'get') {
                $data = $request->getQueryParams();                
            } elseif ($var === 'post') {
                $data = $request->getParsedBody();
            } elseif ($var === 'cookie') {
                $data = $request->getCookieParams();
            }

            $value = isset($data[$key]) ? [$data[$key]] : null;
            return true;
        }
        
        return false;
    }

    /**
     * Bind variable when option is a request header
     * 
     * @param string                 $option
     * @param ServerRequestInterface $request
     * @param mixed                  $value   OUTPUT
     * @return boolean
     */
    protected function bindVarRequestHeader($option, ServerRequestInterface $request, &$value)
    {
        if (preg_match('/^\$(?:HTTP_)?([A-Z_]+)$/', $option, $matches)) {
            $sentence = preg_replace('/[\W_]+/', ' ', $matches[1]);
            $name = str_replace(' ', '-', ucwords($sentence));
            
            $value = [$request->getHeaderLine($name)];
            return true;
        }
        
        return false;
    }
    
    /**
     * Bind variable when option contains multiple URL parts
     * 
     * @param string $option
     * @param string $type    'assoc' or 'numeric'
     * @param array  $parts   Url parts
     * @param mixed  $value   OUTPUT
     * @return boolean
     */
    protected function bindVarMultipleUrlParts($option, $type, array $parts, &$value)
    {
        if (substr($option, -3) === '...' && ctype_digit(substr($option, 1, -3))) {
            $i = (int)substr($option, 1, -3);

            if ($type === 'assoc') {
                throw new \InvalidArgumentException("Binding multiple parts using '$option' is only allowed in numeric arrays");
            } else {
                $value = array_slice($parts, $i - 1);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Bind variable when option contains a single URL part
     * 
     * @param string $option
     * @param array  $parts   Url parts
     * @param mixed  $value   OUTPUT
     * @return boolean
     */
    protected function bindVarSingleUrlPart($option, array $parts, &$value)
    {
        if (ctype_digit(substr($option, 1))) {
            $i = (int)substr($option, 1);
            $part = array_slice($parts, $i - 1, 1);

            if (!empty($part)) {
                $value = $part;
                return true;
            }
        }
        
        return false;
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
            $route = $this->bind($route, $request, $this->splitUrl($url));
        }
        
        return $route;
    }
}
