<?php

class wfUtils {
    public static function patternToRegex($pattern, $mod = 'i', $sep = '/') {
        $pattern = preg_quote(trim($pattern), $sep);
        $pattern = str_replace(' ', '\s', $pattern);
        return $sep . '^' . str_replace('\*', '.*', $pattern) . '$' . $sep . $mod;
    }
    public static function hasLoginCookie(){
        if(isset($_COOKIE)){
            if(is_array($_COOKIE)){
                foreach($_COOKIE as $key => $val){
                    if(strpos($key, 'wordpress_logged_in') === 0){
                        return true;
                    }
                }
            }
        }
        return false;
    }
    public static function setcookie($name, $value, $expire, $path, $domain, $secure, $httpOnly){
        if(version_compare(PHP_VERSION, '5.2.0') >= 0){
            @setcookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);
        } else {
            @setcookie($name, $value, $expire, $path);
        }
    }
    public static function getLastError(){
        $err = error_get_last();
        if(is_array($err)){
            return $err['message'];
        }
        return '';
    }
}
