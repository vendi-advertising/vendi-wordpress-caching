<?php

namespace Vendi\Cache\Legacy;

class wfUtils
{
    public static function hasLoginCookie()
    {
        if( isset( $_COOKIE ) )
        {
            if( is_array( $_COOKIE ) )
            {
                foreach( $_COOKIE as $key => $val )
                {
                    if( strpos( $key, 'wordpress_logged_in' ) === 0 )
                    {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public static function getLastError()
    {
        $err = error_get_last();
        if( is_array( $err ) )
        {
            return $err[ 'message' ];
        }
        return '';
    }

    public static function isAdmin( $user = false )
    {
        if( $user )
        {
            if( is_multisite() )
            {
                if( user_can( $user, 'manage_network' ) )
                {
                    return true;
                }
            }
            else
            {
                if( user_can( $user, 'manage_options' ) )
                {
                    return true;
                }
            }
        }
        else
        {
            if( is_multisite() )
            {
                if( current_user_can( 'manage_network' ) )
                {
                    return true;
                }
            }
            else
            {
                if( current_user_can( 'manage_options' ) )
                {
                    return true;
                }
            }
        }
        return false;
    }
    public static function isAdminPageMU()
    {
        if( preg_match( '/^[\/a-zA-Z0-9\-\_\s\+\~\!\^\.]*\/wp-admin\/network\//', $_SERVER[ 'REQUEST_URI' ] ) )
        {
            return true;
        }
        return false;
    }
    public static function getBaseURL()
    {
        return plugins_url( '', VENDI_CACHE_FCPATH ) . '/';
    }
    public static function isNginx()
    {
        $sapi = php_sapi_name();
        $serverSoft = $_SERVER[ 'SERVER_SOFTWARE' ];
        if( $sapi == 'fpm-fcgi' && stripos( $serverSoft, 'nginx' ) !== false )
        {
            return true;
        }
    }
}
