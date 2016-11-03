<?php

namespace Jasny\Router\Routes;

use Jasny\Router\Route;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Functionality to set route properties based on url parameters
 */
trait RouteBinding
{
    /**
     * Fill out the routes variables based on the url parts.
     * 
     * @param array|\stdClass         $vars     Route variables
     * @param ServerRequestInterface  $request
     * @param array                   $parts    URL parts
     * @return array
     */
    protected function bind($vars, ServerRequestInterface $request, array $parts)
    {
        $type = is_array($vars) && array_keys($vars) === array_keys(array_keys($vars)) ? 'numeric' : 'assoc';

        $values = $this->bindParts($vars, $type, $request, $parts);

        if ($vars instanceof Route) {
            $class = get_class($vars);
            $values = new $class($values);
        } elseif (is_object($vars) && $type === 'assoc') {
            $values = (object)$values;
        }
        
        return $values;
    }

    
    /**
     * Fill out the values based on the url parts.
     * 
     * @param array|\stdClass         $vars     Route variables
     * @param string                  $type
     * @param ServerRequestInterface  $request
     * @param array                   $parts    URL parts
     * @return array
     */
    protected function bindParts($vars, $type, ServerRequestInterface $request, array $parts)
    {
        $values = [];
        
        foreach ($vars as $key => $var) {
            $part = null;
            
            $bound =
                $this->bindPartObject($var, $part) ||
                $this->bindPartArray($var, $request, $parts, $part) ||
                $this->bindPartVar($var, $type, $request, $parts, $part) ||
                $this->bindPartConcat($var, $request, $parts, $part) ||
                $this->bindPartValue($var, $part);
            
            if (!$bound) continue;
            
            if ($type === 'assoc') {
                $values[$key] = $part[0];
            } else {
                $values = array_merge($values, $part);
            }
        }

        return $values;
    }
    
    /**
     * Bind part if it's an object
     * 
     * @param mixed  $var
     * @param array  $part  OUTPUT
     * @return boolean
     */
    protected function bindPartObject($var, &$part)
    {
        if (!is_object($var) || $var instanceof \stdClass) {
            return false;
        }
        
        $part = [$var];
        return true;
    }
    
    /**
     * Bind part if it's an array
     * 
     * @param mixed                  $var
     * @param ServerRequestInterface $request
     * @param array                  $parts
     * @param array                  $part     OUTPUT
     * @return boolean
     */
    protected function bindPartArray($var, ServerRequestInterface $request, array $parts, &$part)
    {
        if (!is_array($var) && !$var instanceof \stdClass) {
            return false;
        }
        
        $part = [$this->bind($var, $request, $parts)];
        return true;
    }
    
    /**
     * Bind part if it's an variable
     * 
     * @param mixed                  $var
     * @param string                 $type
     * @param ServerRequestInterface $request
     * @param array                  $parts
     * @param array                  $part     OUTPUT
     * @return boolean
     */
    protected function bindPartVar($var, $type, ServerRequestInterface $request, array $parts, &$part)
    {
        if (!is_string($var) || $var[0] !== '$') {
            return false;
        }
        
        $options = array_map('trim', explode('|', $var));
        $part = $this->bindVar($type, $request, $parts, $options);
        return true;
    }
    
    /**
     * Bind part if it's an concatenation
     * 
     * @param mixed                  $var
     * @param ServerRequestInterface $request
     * @param array                  $parts
     * @param array                  $part     OUTPUT
     * @return boolean
     */
    protected function bindPartConcat($var, ServerRequestInterface $request, array $parts, &$part)
    {
        if (!is_string($var) || $var[0] !== '~' || substr($var, -1) !== '~') {
            return false;
        }
        
        $pieces = array_map('trim', explode('~', substr($var, 1, -1)));
        $bound = array_filter($this->bind($pieces, $request, $parts));
        $part = [join('', $bound)];
        
        return true;
    }
    
    /**
     * Bind part if it's a normal value
     * 
     * @param mixed $var
     * @param array $part  OUTPUT
     * @return boolean
     */
    protected function bindPartValue($var, &$part)
    {
        if (!isset($var)) {
            return false;
        }
        
        $part = [$var];
        return true;
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
}
