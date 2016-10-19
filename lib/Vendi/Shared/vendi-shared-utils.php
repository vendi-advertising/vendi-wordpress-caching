<?php
/*
Plugin Name: Vendi Shared Utility Class
Description: Helper class shared across all Vendi-controlled properties.
Version: 3.0.3
Author: Vendi
*/

namespace Vendi\Shared;

if (class_exists('\Vendi\Shared\utils'))
{
    return;
}

/**
 * Utility class generally for HTTP.
 *
 * NOTE: Do not modify any methods in this class ever. You
 * can add new methods as needed but there is a lot of code
 * the depends on this functioning in a specific fashion and
 * since this is a shared class you are not guarenteed to have
 * this specific class actually loaded.
 *
 * To clarify the above, this class is intended to be used by
 * multiple plugins and there is no guarantee of load order.
 * If you add a new method to this class you should grep the
 * server for other installs and add code to those, too, since
 * you don't know if your code will load first.
 *
 * Any additional methods to this class MUST work without fail
 * and can have zero dependencies upon other code.
 *
 * History:
 *
 * 2.0.1 - Added unparse_url()
 *  
 * 2.0.0 - Rewrite of previous code into this namesapce
 * 
 * @version  2.0.1
 */

class utils
{
    /**
     * Get the value from the HTTP POST return the $default_value.
     * @param  string        $key           The form field's name to search in the $_POST array for.
     * @param  integer|mixed $default_value Optional. If the $key cannot be found the value to return. Default null.
     * @return integer|mixed                The value of the HTTP POST for the given $key or the $default.
     */
    public static function get_post_value($key, $default_value = '')
    {
        return self::get_request_value('POST', $key, $default_value);
    }

    /**
     * Get the value from the HTTP GET return the $default_value.
     * @param  string        $key           The form field's name to search in the $_GET array for.
     * @param  integer|mixed $default_value Optional. If the $key cannot be found the value to return. Default null.
     * @return integer|mixed                The value of the HTTP GET for the given $key or the $default.
     */
    public static function get_get_value($key, $default_value = '')
    {
        return self::get_request_value('GET', $key, $default_value);
    }

    /**
     * Get the value from the HTTP COOKIE return the $default_value.
     * @param  string        $key           The form field's name to search in the $_COOKIE array for.
     * @param  integer|mixed $default_value Optional. If the $key cannot be found the value to return. Default null.
     * @return integer|mixed                The value of the HTTP COOKIE for the given $key or the $default.
     */
    public static function get_cookie_value($key, $default_value = '')
    {
        return self::get_request_value('COOKIE', $key, $default_value);
    }

    /**
     * Get the value from the HTTP SERVER return the $default_value.
     * @param  string        $key           The form field's name to search in the $_SERVER array for.
     * @param  integer|mixed $default_value Optional. If the $key cannot be found the value to return. Default null.
     * @return integer|mixed                The value of the HTTP SERVER for the given $key or the $default.
     */
    public static function get_server_value($key, $default_value = '')
    {
        return self::get_request_value('SERVER', $key, $default_value);
    }

    /**
     * Get the value from the HTTP POST as an integer or return the $default_value.
     * @param  string        $key           The form field's name to search in the $_POST array for.
     * @param  integer|mixed $default_value Optional. If the $key cannot be found the value to return. Default null.
     * @return integer|mixed                The value of the HTTP POST for the given $key or the $default.
     */
    public static function get_post_value_int($key, $default_value = null)
    {
        return self::get_request_value_int('POST', $key, $default_value);
    }

    /**
     * Get the value from the HTTP GET as an integer or return the $default_value.
     * @param  string        $key           The form field's name to search in the $_GET array for.
     * @param  integer|mixed $default_value Optional. If the $key cannot be found the value to return. Default null.
     * @return integer|mixed                The value of the HTTP GET for the given $key or the $default.
     */
    public static function get_get_value_int($key, $default_value = null)
    {
        return self::get_request_value_int('GET', $key, $default_value);
    }

    /**
     * Get the value from the HTTP COOKIE as an integer or return the $default_value.
     * @param  string        $key           The form field's name to search in the $_COOKIE array for.
     * @param  integer|mixed $default_value Optional. If the $key cannot be found the value to return. Default null.
     * @return integer|mixed                The value of the HTTP COOKIE for the given $key or the $default.
     */
    public static function get_cookie_value_int($key, $default_value = null)
    {
        return self::get_request_value_int('COOKIE', $key, $default_value);
    }

    /**
     * Get the value from the HTTP SERVER as an integer or return the $default_value.
     * @param  string        $key           The form field's name to search in the $_SERVER array for.
     * @param  integer|mixed $default_value Optional. If the $key cannot be found the value to return. Default null.
     * @return integer|mixed                The value of the HTTP SERVER for the given $key or the $default.
     */
    public static function get_server_value_int( $key, $default_value = null )
    {
        return self::get_request_value_int( 'SERVER', $key, $default_value );
    }

    /**
     * @param string $request_method
     * @param string $key
     */
    public static function get_request_value_int( $request_method, $key, $default_value = null )
    {
        $value = self::get_request_value( $request_method, $key, null );
        if( self::is_integer_like( $value ) )
        {
            return (int)$value;
        }

        return $default_value;
    }

    public static function get_request_value( $request_method, $key, $default_value = null )
    {
        $request_obj = self::get_request_object( $request_method );
        if( null === $request_obj || ! is_array( $request_obj ) || ! array_key_exists( $key, $request_obj ) )
        {
            return $default_value;
        }

        return trim( $request_obj[ $key ] );
    }

    public static function get_request_object( $request_method )
    {
        $obj = null;
        switch( $request_method )
        {
            case 'GET':
                return ( isset( $_GET ) && is_array( $_GET ) && count( $_GET ) > 0 ? $_GET : null );
            case 'POST':
                return ( isset( $_POST ) && is_array( $_POST ) && count( $_POST ) > 0 ? $_POST : null );
            case 'COOKIE':
                return ( isset( $_COOKIE ) && is_array( $_COOKIE ) && count( $_COOKIE ) > 0 ? $_COOKIE : null );
            case 'SERVER':
                return ( isset( $_SERVER ) && is_array( $_SERVER ) && count( $_SERVER ) > 0 ? $_SERVER : null );
            default:
                return null;
        }
    }

    /**
     * Test if we're in a certain type of HTTP request.
     * 
     * @param  string  $method The server method to test for. Generally one of GET, POST, HEAD, PUT, DELETE.
     * @return boolean         Returns true if the REQUEST_METHOD server variable is set to the supplied $method, otherwise false.
     */
    public static function is_request_method($method)
    {
        return $method === self::get_server_value('REQUEST_METHOD');
    }

    /**
     * Check to see if we're in a post.
     *
     * Unit tests were failing because REQUEST_METHOD wasn't always being set. This should be used
     * for all POST checks.
     *
     * \Vendi\Forms\utils::is_post()
     * 
     * @return boolean Returns true if the REQUEST_METHOD server variable is set to POST, otherwise false.
     */
    public static function is_post()
    {
        return self::is_request_method('POST');
    }

    /**
     * Test if the given $input can be converted to an int excluding booleans.
     *
     * \Vendi\Forms\utils::is_integer_like( value )
     * 
     * @param  mixed  $input The value to test.
     * @return boolean       True if $input is an integer or a string that contains only digits possibly starting with a dash.
     */
    public static function is_integer_like($input)
    {
        return
                is_int($input)
                ||
                (
                    is_string($input)
                    &&
                    preg_match('/^-?([0-9])+$/', $input)
                );
    }

    public static function get_all_headers()
    {
        $headers = array();
        foreach (array('_SERVER', '_GET', '_POST', '_COOKIE', '_ENV') as $key)
        {
            if (array_key_exists($key, $GLOBALS))
            {

            }
            $headers[$key] = $GLOBALS[$key];
        }
        return $headers;
    }

    /**
     * Convert the result of urlpieces back to a URL.
     *
     * @see  http://php.net/manual/en/function.parse-url.php#106731
     *
     * @param  array $parsed_url An array created by urlpieces.
     * @return string            A URL string.
     */
    public static function unparse_url($parsed_url) {
        //I don't know what you gave me so you can just have it back
        if ( ! is_array($parsed_url)) {
            return $parsed_url;
        }
        $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

        //NOTE: user and pass were explicitly removed.

        return "$scheme$host$port$path$query$fragment";
    }

}
