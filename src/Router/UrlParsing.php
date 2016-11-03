<?php

namespace Jasny\Router;

/**
 * Basic URL parsing helper methods
 */
trait UrlParsing
{
    /**
     * Get parts of a URL path
     * 
     * @param string $url
     * @return array
     */
    protected function splitUrl($url)
    {
        $path = parse_url(trim($url, '/'), PHP_URL_PATH);
        return $path ? explode('/', $path) : array();
    }
    
    /**
     * Clean up the URL
     * 
     * @param string $url
     * @return string
     */
    protected function cleanUrl($url)
    {
        if ($url !== '/') {
            $url = rtrim($url, '/');
        }
        
        return $url;
    }
}
