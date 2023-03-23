<?php

namespace eru123\router;

class URL {

    /**
     * check if URL matched with regex
     * @param string $rgx URL Regex
     * @param ?string $url URL to check, if null, it will use current URL
     * @return bool
     */
    public static function matched($rgx, $url = null)
    {
        $urlp = !empty($url)? static::sanitize_uri($url) : static::current();
        $rgxp = static::create_param_regex($rgx);
        return preg_match($rgxp, $urlp);
    }

    /**
     * Extract parameters from URL
     * @param string $rgx URL Regex
     * @param ?string $url URL to check, if null, it will use current URL
     * @return array
     */
    public static function params($rgx, $url = null)
    {
        $urlp = !empty($url)? static::sanitize_uri($url) : static::current();
        $rgxp = static::create_param_regex($rgx);
        preg_match($rgxp, $urlp, $matches);
        return array_filter($matches, function($k) {
            return !is_numeric($k);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Create parameterized regex from URL
     * @param string $url URL to create regex
     * @return string URL Regex
     */
    public static function create_param_regex($url)
    {
        $url = static::sanitize_uri($url);
        $rgxp = '/\$([a-zA-Z0-9_]+)/';
        $rgx = preg_replace('/\//', "\\\/", $url);
        $rgx = preg_replace($rgxp, '(?P<$1>[^\/\?]+)', $rgx);
        return '/^' . $rgx . '$/';
    }

    /**
     * get current URI
     * @return string
     */
    public static function current()
    {
        return static::sanitize_uri($_SERVER['REQUEST_URI']);
    }

    /**
     * Filter URI
     * @param string $uri URI to filter
     * @return string
     */
    public static function sanitize_uri($uri)
    {
        $uri = preg_replace('/\?.*/', '', $uri);
        $uri = trim($uri, '/');
        return $uri;
    }
}