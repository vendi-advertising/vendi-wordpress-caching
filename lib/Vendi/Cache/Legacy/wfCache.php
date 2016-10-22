<?php

namespace Vendi\Cache\Legacy;

use Vendi\Cache\cache_settings;
use Vendi\Cache\utils;

class wfCache
{
    private static $cacheType = false;
    private static $fileCache = array();
    private static $cacheStats = array();
    private static $cacheClearedThisRequest = false;
    private static $clearScheduledThisRequest = false;
    private static $lastRecursiveDeleteError = false;

    private static $vwc_cache_settings;

    public static function get_vwc_cache_settings()
    {
        if( ! self::$vwc_cache_settings )
        {
            self::$vwc_cache_settings = new cache_settings();
        }

        return self::$vwc_cache_settings;

    }

    public static function setup_caching()
    {
        self::$cacheType = self::get_vwc_cache_settings()->get_cache_mode();
        if( self::$cacheType != cache_settings::CACHE_MODE_PHP && self::$cacheType != cache_settings::CACHE_MODE_ENHANCED )
        {
            return; //cache is disabled
        }
        if( wfUtils::hasLoginCookie() )
        {
            add_action( 'publish_post', array( __CLASS__, 'action_publish_post' ) );
            add_action( 'publish_page', array( __CLASS__, 'action_publish_post' ) );
            foreach( array( 'clean_object_term_cache', 'clean_post_cache', 'clean_term_cache', 'clean_page_cache', 'after_switch_theme', 'customize_save_after', 'activated_plugin', 'deactivated_plugin', 'update_option_sidebars_widgets' ) as $action )
            {
                add_action( $action, array( __CLASS__, 'action_clear_page_cache' ) ); //Schedules a cache clear for immediately so it won't lag current request.
            }
            if( $_SERVER[ 'REQUEST_METHOD' ] == 'POST' )
            {
                foreach( array(
                    '/\/wp\-admin\/options\.php$/',
                    '/\/wp\-admin\/options\-permalink\.php$/'
                    ) as $pattern )
                {
                    if( preg_match( $pattern, $_SERVER[ 'REQUEST_URI' ] ) )
                    {
                        self::schedule_cache_clear();
                        break;
                    }
                }
            }
        }
        add_action( 'wordfence_cache_clear', array( __CLASS__, 'scheduled_cache_clear' ) );
        add_action( 'comment_post', array( __CLASS__, 'action_comment_post' ) ); //Might not be logged in
        add_filter( 'wp_redirect', array( __CLASS__, 'redirect_filter' ) );

        //Routines to clear cache run even if cache is disabled
        $file = self::file_from_request( ( $_SERVER[ 'HTTP_HOST' ] ? $_SERVER[ 'HTTP_HOST' ] : $_SERVER[ 'SERVER_NAME' ] ), $_SERVER[ 'REQUEST_URI' ] );
        $fileDeleted = false;
        $doDelete = false;
        if( $_SERVER[ 'REQUEST_METHOD' ] != 'GET' )
        {
//If our URL is hit with a POST, PUT, DELETE or any other non 'GET' request, then clear cache.
            $doDelete = true;
        }

        if( $doDelete )
        {
            @unlink( $file );
            $fileDeleted = true;
        }

        if( self::is_cachable() )
        {
            if( ( ! $fileDeleted ) && self::$cacheType == cache_settings::CACHE_MODE_PHP )
            {
//Then serve the file if it's still valid
                $stat = @stat( $file );
                if( $stat )
                {
                    $age = time() - $stat[ 9 ];
                    if( $age < 10000 )
                    {
                        readfile( $file ); //sends file to stdout
                        die();
                    }
                }
            }
            ob_start( array( __CLASS__, 'ob_complete' ) ); //Setup routine to store the file
        }
    }
    public static function redirect_filter( $status )
    {
        if( ! defined( 'WFDONOTCACHE' ) )
        {
            define( 'WFDONOTCACHE', true );
        }
        return $status;
    }

    public static function is_a_no_cache_constant_defined()
    {
        //If you want to tell Wordfence not to cache something in another plugin, simply define one of these. 
        return defined( 'WFDONOTCACHE' ) || defined( 'DONOTCACHEPAGE' ) || defined( 'DONOTCACHEDB' ) || defined( 'DONOTCACHEOBJECT' );
    }

    public static function is_cachable_test_exclusions()
    {
        
        $ex = self::get_vwc_cache_settings()->get_cache_exclusions();
        if( ! $ex || ! is_array( $ex ) || 0 === count( $ex ) )
        {
            return true;
        }
        
        $user_agent = utils::get_server_value( 'HTTP_USER_AGENT', '' );
        $uri = utils::get_server_value( 'REQUEST_URI', '' );

        foreach( $ex as $v )
        {
            if( $v[ 'pt' ] == 'eq' )
            {
                if( strtolower( $uri ) == strtolower( $v[ 'p' ] ) )
                {
                    return false;
                }
            }

            if( $v[ 'pt' ] == 's' )
            {
                if( stripos( $uri, $v[ 'p' ] ) === 0 )
                {
                    return false;
                }
            }

            if( $v[ 'pt' ] == 'e' )
            {
                if( stripos( $uri, $v[ 'p' ] ) === ( strlen( $uri ) - strlen( $v[ 'p' ] ) ) )
                {
                    return false;
                }
            }

            if( $v[ 'pt' ] == 'c' )
            {
                if( stripos( $uri, $v[ 'p' ] ) !== false )
                {
                    return false;
                }
            }

            //User-agent contains
            if( $v[ 'pt' ] == 'uac' )
            {
                if( stripos( $user_agent, $v[ 'p' ] ) !== false )
                {
                    return false;
                }
            }

            //user-agent equals
            if( $v[ 'pt' ] == 'uaeq' )
            {
                if( strtolower( $user_agent ) == strtolower( $v[ 'p' ] ) )
                {
                    return false;
                }
            }

            if( $v[ 'pt' ] == 'cc' )
            {

                $cookies = utils::get_request_object( 'COOKIE' );

                if( is_array( $cookies ) )
                {
                    foreach( $cookies as $cookieName )
                    {
                        //Cookie name contains pattern
                        if( stripos( $cookieName, $v[ 'p' ] ) !== false )
                        {
                            return false;
                        }
                    }
                }
            }
        }

        return true;
    }

    public static function is_cachable()
    {

        if( self::is_a_no_cache_constant_defined() )
        {
            return false;
        }

        if( ! self::get_vwc_cache_settings()->get_do_cache_https_urls() && self::is_https_page() )
        {
            return false;
        }

        //dont cache any admin pages.
        if( is_admin() )
        {
            return false;
        }

        $uri = utils::get_server_value( 'REQUEST_URI', '' );

        //must end with a '/' char.
        if( strrpos( $uri, '/' ) !== strlen( $uri ) - 1 )
        {
            return false;
        }

        //Only cache GET's
        if( ! utils::is_request_method( 'GET' ) )
        {
            return false;
        }

        $query_string = utils::get_server_value( 'QUERY_STRING', '' );

        //TODO: Do we still need/want this?
        //Don't cache query strings unless they are /?123132423=123123234 DDoS style.
        if( strlen( $query_string ) > 0 && ( ! preg_match( '/^\d+=\d+$/', $query_string ) ) )
        {
            return false; 
        }

        $cookies = utils::get_request_object( 'COOKIE' );

        //wordpress_logged_in_[hash] cookies indicates logged in
        if( is_array( $cookies ) )
        {
            foreach( array_keys( $cookies ) as $c )
            {
                foreach( array( 'comment_author', 'wp-postpass', 'wf_logout', 'wordpress_logged_in', 'wptouch_switch_toggle', 'wpmp_switcher' ) as $b )
                {
                    //contains a cookie which indicates user must not be cached
                    if( strpos( $c, $b ) !== false )
                    {
                        return false;
                    }
                }
            }
        }
        
        if( ! self::is_cachable_test_exclusions() )
        {
            return false;
        }
        return true;
    }

    /**
     * @return boolean True if the reqeusted page was an HTTPS page, otherwise false.
     */
    public static function is_https_page()
    {
        //Prefer a core check since this is in flux right now
        if( is_ssl() )
        {
            return true;
        }

        //In case we're behind a proxy and user used HTTPS.
        if( 'https' === utils::get_server_value( 'HTTP_X_FORWARDED_PROTO' ) )
        {
            return true;
        }

        return false;
    }

    public static function ob_complete( $buffer = '' )
    {
        if( function_exists( 'is_404' ) && is_404() )
        {
            return false;
        }

        //These constants may have been set after we did the initial is_cachable check by e.g. wp_redirect filter. If they're set then just return the buffer and don't cache.
        if( self::is_a_no_cache_constant_defined() )
        {
            return $buffer; 
        }

        //The average web page size is 1246,000 bytes. If web page is less than 1000 bytes, don't cache it. 
        //TODO: Move to option
        if( strlen( $buffer ) < 1000 )
        {
            return $buffer;
        }

        $file = self::file_from_request( utils::get_server_value( 'HTTP_HOST' ) ? utils::get_server_value( 'SERVER_NAME' ) : utils::get_server_value( 'REQUEST_URI' ) );
        self::make_dir_if_needed( $file );
        // self::writeCacheDirectoryHtaccess();
        $append = "";
        $appendGzip = "";
        if( self::get_vwc_cache_settings()->get_do_append_debug_message() )
        {
            $append = "\n<!-- Cached by Wordfence ";
            if( self::get_vwc_cache_settings()->get_cache_mode() == cache_settings::CACHE_MODE_ENHANCED )
            {
                $append .= "Falcon Engine. ";
            }
            else
            {
                $append .= "PHP Caching Engine. ";
            }
            $append .= "Time created on server: " . date( 'Y-m-d H:i:s T' ) . ". ";
            $append .= "Is HTTPS page: " . ( self::is_https_page() ? 'HTTPS' : 'no' ) . ". ";
            $append .= "Page size: " . strlen( $buffer ) . " bytes. ";
            $append .= "Host: " . ( $_SERVER[ 'HTTP_HOST' ] ? wp_kses( $_SERVER[ 'HTTP_HOST' ], array() ) : wp_kses( $_SERVER[ 'SERVER_NAME' ], array() ) ) . ". ";
            $append .= "Request URI: " . wp_kses( $_SERVER[ 'REQUEST_URI' ], array() ) . " ";
            $appendGzip = $append . " Encoding: GZEncode -->\n";
            $append .= " Encoding: Uncompressed -->\n";
        }

        @file_put_contents( $file, $buffer . $append, LOCK_EX );
        chmod( $file, 0644 );
        if( self::$cacheType == cache_settings::CACHE_MODE_ENHANCED )
        {
//create gzipped files so we can send precompressed files
            $file .= '_gzip';
            @file_put_contents( $file, gzencode( $buffer . $appendGzip, 9 ), LOCK_EX );
            chmod( $file, 0644 );
        }
        return $buffer;
    }

    public static function file_from_request( $host, $URI )
    {
        return self::file_from_uri( $host, $URI, self::is_https_page() );
    }

    /**
     * @param boolean $isHTTPS
     *
     * @return string
     */
    public static function file_from_uri( $host, $URI, $isHTTPS )
    {
        $key = $host . $URI . ( $isHTTPS ? '_HTTPS' : '' );
        if( isset( self::$fileCache[ $key ] ) )
        {
return self::$fileCache[ $key ]; }
        $host = preg_replace( '/[^a-zA-Z0-9\-\.]+/', '', $host );
        $URI = preg_replace( '/(?:[^a-zA-Z0-9\-\_\.\~\/]+|\.{2,})/', '', $URI ); //Strip out bad chars and multiple dots
        if( preg_match( '/\/*([^\/]*)\/*([^\/]*)\/*([^\/]*)\/*([^\/]*)\/*([^\/]*)(.*)$/', $URI, $matches ) )
        {
            $URI = $matches[ 1 ] . '/';
            for( $i = 2; $i <= 6; $i++ )
            {
                $URI .= strlen( $matches[ $i ] ) > 0 ? $matches[ $i ] : '';
                $URI .= $i < 6 ? '~' : '';
            }
        }
        $ext = '';
        if( $isHTTPS )
        {
$ext = '_https'; }
        $file = WP_CONTENT_DIR . '/wfcache/' . $host . '_' . $URI . '_wfcache' . $ext . '.html';
        self::$fileCache[ $key ] = $file;
        return $file;
    }

    /**
     * @param string $file
     */
    public static function make_dir_if_needed( $file )
    {
        $file = preg_replace( '/\/[^\/]*$/', '', $file );
        if( ! is_dir( $file ) )
        {
            @mkdir( $file, 0755, true );
        }
    }

    public static function cache_directory_test()
    {
        $cacheDir = WP_CONTENT_DIR . '/wfcache/';
        if( ! is_dir( $cacheDir ) )
        {
            if( ! @mkdir( $cacheDir, 0755, true ) )
            {
                $err = error_get_last();
                $msg = "The directory $cacheDir does not exist and we could not create it.";
                if( $err )
                {
                    $msg .= ' The error we received was: ' . $err[ 'message' ];
                }
                return $msg;
            }
        }
        if( ! @file_put_contents( $cacheDir . 'test.php', 'test' ) )
        {
            $err = error_get_last();
            $msg = "We could not write to the file $cacheDir" . "test.php when testing if the cache directory is writable.";
            if( $err )
            {
                $msg .= " The error was: " . $err[ 'message' ];
            }
            return $msg;
        }
        self::remove_cache_directory_htaccess();
        return false;
        // return self::writeCacheDirectoryHtaccess(); //Everything is OK
    }

    public static function remove_cache_directory_htaccess()
    {
        $cacheDir = WP_CONTENT_DIR . '/wfcache/';
        if( file_exists( $cacheDir . '.htaccess' ) )
        {
            unlink( $cacheDir . '.htaccess' );
        }
    }

    public static function action_publish_post( $id )
    {
        $perm = get_permalink( $id );
        self::delete_file_from_permalink( $perm );
        self::schedule_cache_clear();
    }

    public static function action_comment_post( $commentID )
    {
        $c = get_comment( $commentID, ARRAY_A );
        $perm = get_permalink( $c[ 'comment_post_ID' ] );
        self::delete_file_from_permalink( $perm );
        self::schedule_cache_clear();
    }

    //Can safely call this as many times as we like because it'll only schedule one clear
    public static function action_clear_page_cache()
    {
        self::schedule_cache_clear();
    }

    public static function schedule_cache_clear()
    {
        if( self::$clearScheduledThisRequest )
        {
return; }
        self::$clearScheduledThisRequest = true;
        wp_schedule_single_event( time() - 15, 'wordfence_cache_clear', array( rand( 0, 999999999 ) ) ); //rand makes sure this is called every time and isn't subject to the 10 minute window where the same event won't be run twice with wp_schedule_single_event
        $url = admin_url( 'admin-ajax.php' );
        wp_remote_get( $url );
    }

    public static function scheduled_cache_clear( $random )
    {
        self::clear_page_cache_safe(); //Will only run if clear_page_cache() has not run this request
    }

    public static function delete_file_from_permalink( $perm )
    {
        if( preg_match( '/\/\/([^\/]+)(\/.*)$/', $perm, $matches ) )
        {
            $host = $matches[ 1 ];
            $uri = $matches[ 2 ];
            $file = self::file_from_request( $host, $uri );
            if( is_file( $file ) )
            {
                @unlink( $file );
            }
        }
    }

    public static function get_cache_stats()
    {
        self::$cacheStats = array(
            'files' => 0,
            'dirs' => 0,
            'data' => 0,
            'compressedFiles' => 0,
            'compressedKBytes' => 0,
            'uncompressedFiles' => 0,
            'uncompressedKBytes' => 0,
            'oldestFile' => false,
            'newestFile' => false,
            'largestFile' => 0,
            );
        self::recursive_stats( WP_CONTENT_DIR . '/wfcache/' );
        return self::$cacheStats;
    }

    /**
     * @param string $dir
     */
    private static function recursive_stats( $dir )
    {
        $files = array_diff( scandir( $dir ), array( '.', '..' ) ); 
        foreach( $files as $file )
        {
            $fullPath = $dir . '/' . $file;
            if( is_dir( $fullPath ) )
            {
                self::$cacheStats[ 'dirs' ]++;
                self::recursive_stats( $fullPath );
            }
            else
            {
                if( $file == 'clear.lock' )
                {
continue; }
                self::$cacheStats[ 'files' ]++;
                $stat = stat( $fullPath );
                if( is_array( $stat ) )
                {
                    $size = $stat[ 7 ];
                    if( $size )
                    {
                        $size = round( $size / 1024 );
                        self::$cacheStats[ 'data' ] += $size;
                        if( strrpos( $file, '_gzip' ) == strlen( $file ) - 6 )
                        {
                            self::$cacheStats[ 'compressedFiles' ]++;
                            self::$cacheStats[ 'compressedKBytes' ] += $size;
                        }
                        else
                        {
                            self::$cacheStats[ 'uncompressedFiles' ]++;
                            self::$cacheStats[ 'uncompressedKBytes' ] += $size;
                        }
                        if( self::$cacheStats[ 'largestFile' ] < $size )
                        {
                            self::$cacheStats[ 'largestFile' ] = $size;
                        }
                    }

                    $ctime = $stat[ 10 ];
                    if( self::$cacheStats[ 'oldestFile' ] > $ctime || self::$cacheStats[ 'oldestFile' ] === false )
                    {
                        self::$cacheStats[ 'oldestFile' ] = $ctime;
                    }
                    if( self::$cacheStats[ 'newestFile' ] === false || self::$cacheStats[ 'newestFile' ] < $ctime )
                    {
                        self::$cacheStats[ 'newestFile' ] = $ctime;
                    }
                }
            }
        }
    }

    public static function clear_page_cache_safe()
    {
        if( self::$cacheClearedThisRequest )
        {
return; }
        self::$cacheClearedThisRequest = true;
        self::clear_page_cache();
    }

    //If a clear is in progress this does nothing. 
    public static function clear_page_cache()
    {
        self::$cacheStats = array(
            'dirsDeleted' => 0,
            'filesDeleted' => 0,
            'totalData' => 0,
            'totalErrors' => 0,
            'error' => '',
            );
        $cacheClearLock = WP_CONTENT_DIR . '/wfcache/clear.lock';
        if( ! is_file( $cacheClearLock ) )
        {
            if( ! touch( $cacheClearLock ) )
            {
                self::$cacheStats[ 'error' ] = "Could not create a lock file $cacheClearLock to clear the cache.";
                self::$cacheStats[ 'totalErrors' ]++;
                return self::$cacheStats;
            }
        }
        $fp = fopen( $cacheClearLock, 'w' );
        if( ! $fp )
        {
            self::$cacheStats[ 'error' ] = "Could not open the lock file $cacheClearLock to clear the cache. Please make sure the directory is writable by your web server.";
            self::$cacheStats[ 'totalErrors' ]++;
            return self::$cacheStats;
        }
        if( flock( $fp, LOCK_EX | LOCK_NB ) )
        {
//non blocking exclusive flock attempt. If we get a lock then it continues and returns true. If we don't lock, then return false, don't block and don't clear the cache. 
                    // This logic means that if a cache clear is currently in progress we don't try to clear the cache.
                    // This prevents web server children from being queued up waiting to be able to also clear the cache. 
            self::$lastRecursiveDeleteError = false;
            self::recursive_delete( WP_CONTENT_DIR . '/wfcache/' );
            if( self::$lastRecursiveDeleteError )
            {
                self::$cacheStats[ 'error' ] = self::$lastRecursiveDeleteError;
                self::$cacheStats[ 'totalErrors' ]++;
            }
            flock( $fp, LOCK_UN );
        }
        fclose( $fp );

        return self::$cacheStats;
    }

    /**
     * @param string $dir
     */
    public static function recursive_delete( $dir )
    {
        $files = array_diff( scandir( $dir ), array( '.', '..' ) ); 
        foreach( $files as $file )
        {
            if( is_dir( $dir . '/' . $file ) )
            {
                if( ! self::recursive_delete( $dir . '/' . $file ) )
                {
                    return false;
                }
            }
            else
            {
                if( $file == 'clear.lock' )
                {
continue; } //Don't delete our lock file
                $size = filesize( $dir . '/' . $file );
                if( $size )
                {
                    self::$cacheStats[ 'totalData' ] += round( $size / 1024 );
                }
                if( strpos( $dir, 'wfcache/' ) === false )
                {
                    self::$lastRecursiveDeleteError = "Not deleting file in directory $dir because it appears to be in the wrong path.";
                    self::$cacheStats[ 'totalErrors' ]++;
                    return false; //Safety check that we're in a subdir of the cache
                }
                if( @unlink( $dir . '/' . $file ) )
                {
                    self::$cacheStats[ 'filesDeleted' ]++;
                }
                else
                {
                    self::$lastRecursiveDeleteError = "Could not delete file " . $dir . "/" . $file . " : " . wfUtils::getLastError();
                    self::$cacheStats[ 'totalErrors' ]++;
                    return false;
                }
            }
        } 
        if( $dir != WP_CONTENT_DIR . '/wfcache/' )
        {
            if( strpos( $dir, 'wfcache/' ) === false )
            {
                self::$lastRecursiveDeleteError = "Not deleting directory $dir because it appears to be in the wrong path.";
                self::$cacheStats[ 'totalErrors' ]++;
                return false; //Safety check that we're in a subdir of the cache
            }
            if( @rmdir( $dir ) )
            {
                self::$cacheStats[ 'dirsDeleted' ]++;
            }
            else
            {
                self::$lastRecursiveDeleteError = "Could not delete directory $dir : " . wfUtils::getLastError();
                self::$cacheStats[ 'totalErrors' ]++;
                return false;
            }
            return true;
        }
        else
        {
            return true;
        }
    }

    /**
     * @param string $action
     */
    public static function add_htaccess_code( $action )
    {
        if( $action != 'add' && $action != 'remove' )
        {
            die( "Error: add_htaccess_code must be called with 'add' or 'remove' as param" );
        }
        $htaccessPath = self::get_htaccess_path();
        if( ! $htaccessPath )
        {
            return "Wordfence could not find your .htaccess file.";
        }
        $fh = @fopen( $htaccessPath, 'r+' );
        if( ! $fh )
        {
            $err = error_get_last();
            return $err[ 'message' ];
        }
        flock( $fh, LOCK_EX );
        fseek( $fh, 0, SEEK_SET ); //start of file
        clearstatcache();
        $contents = fread( $fh, filesize( $htaccessPath ) );
        if( ! $contents )
        {
            fclose( $fh );
            return "Could not read from $htaccessPath";
        }
        $contents = preg_replace( '/#WFCACHECODE.*WFCACHECODE[\r\s\n\t]*/s', '', $contents );
        if( $action == 'add' )
        {
            $code = self::get_htaccess_code();
            $contents = $code . "\n" . $contents;
        }
        ftruncate( $fh, 0 );
        fflush( $fh );
        fseek( $fh, 0, SEEK_SET );
        fwrite( $fh, $contents );
        flock( $fh, LOCK_UN );
        fclose( $fh );
        return false;
    }

    public static function get_htaccess_code()
    {
        $siteURL = site_url();
        $homeURL = home_url();
        $pathPrefix = "";
        if( preg_match( '/^https?:\/\/[^\/]+\/(.+)$/i', $siteURL, $matches ) )
        {
            $path = $matches[ 1 ];
            $path = preg_replace( '/^\//', '', $path );
            $path = preg_replace( '/\/$/', '', $path );
            $pathPrefix = '/' . $path; // Which is: /my/path
        }
        $matchCaps = '$1/$2~$3~$4~$5~$6';
        if( preg_match( '/^https?:\/\/[^\/]+\/(.+)$/i', $homeURL, $matches ) )
        {
            $path = $matches[ 1 ];
            $path = preg_replace( '/^\//', '', $path );
            $path = preg_replace( '/\/$/', '', $path );
            $pieces = explode( '/', $path );
            if( count( $pieces ) == 1 )
            {
                # No path:       "/wp-content/wfcache/%{HTTP_HOST}_$1/$2~$3~$4~$5~$6_wfcache%{ENV:WRDFNC_HTTPS}.html%{ENV:WRDFNC_ENC}" [L]
                # One path:  "/mdm/wp-content/wfcache/%{HTTP_HOST}_mdm/$1~$2~$3~$4~$5_wfcache%{ENV:WRDFNC_HTTPS}.html%{ENV:WRDFNC_ENC}" [L]
                $matchCaps = $pieces[ 0 ] . '/$1~$2~$3~$4~$5';
            }
            else if( count( $pieces ) == 2 )
            {
                $matchCaps = $pieces[ 0 ] . '/' . $pieces[ 1 ] . '/$1~$2~$3~$4';
            }
            else
            {
                $matchCaps = '$1/$2~$3~$4~$5~$6'; #defaults to the regular setting but this won't work. However user should already have gotten a warning that we don't support sites more than 2 dirs deep with falcon.
            }
        }
        $sslString = "RewriteCond %{HTTPS} off";
        if( self::get_vwc_cache_settings()->get_do_cache_https_urls() )
        {
            $sslString = "";
        }
        $otherRewriteConds = "";
        $ex = self::get_vwc_cache_settings()->get_cache_exclusions();
        if( $ex && count( $ex ) > 0 )
        {
            foreach( $ex as $v )
            {
                if( $v[ 'pt' ] == 'uac' )
                {
                    $otherRewriteConds .= "\n\tRewriteCond %{HTTP_USER_AGENT} !" . self::regex_space_fix( preg_quote( $v[ 'p' ] ) ) . " [NC]";
                }
                if( $v[ 'pt' ] == 'uaeq' )
                {
                    $otherRewriteConds .= "\n\tRewriteCond %{HTTP_USER_AGENT} !^" . self::regex_space_fix( preg_quote( $v[ 'p' ] ) ) . "$ [NC]";
                }
                if( $v[ 'pt' ] == 'cc' )
                {
                    $otherRewriteConds .= "\n\tRewriteCond %{HTTP_COOKIE} !" . self::regex_space_fix( preg_quote( $v[ 'p' ] ) ) . " [NC]";
                }
            }
        }

        $code = <<<EOT
#WFCACHECODE - Do not remove this line. Disable Web Caching in Wordfence to remove this data.
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/css text/x-component application/x-javascript application/javascript text/javascript text/x-js text/html text/richtext image/svg+xml text/plain text/xsd text/xsl text/xml image/x-icon application/json
    <IfModule mod_headers.c>
        Header append Vary User-Agent env=!dont-vary
    </IfModule>
    <IfModule mod_mime.c>
        AddOutputFilter DEFLATE js css htm html xml
    </IfModule>
</IfModule>
<IfModule mod_mime.c>
    AddType text/html .html_gzip
    AddEncoding gzip .html_gzip
    AddType text/xml .xml_gzip
    AddEncoding gzip .xml_gzip
</IfModule>
<IfModule mod_setenvif.c>
    SetEnvIfNoCase Request_URI \.html_gzip$ no-gzip
    SetEnvIfNoCase Request_URI \.xml_gzip$ no-gzip
</IfModule>
<IfModule mod_headers.c>
    Header set Vary "Accept-Encoding, Cookie"
</IfModule>
<IfModule mod_rewrite.c>
    #Prevents garbled chars in cached files if there is no default charset.
    AddDefaultCharset utf-8

    #Cache rules:
    RewriteEngine On
    RewriteBase /
    RewriteCond %{HTTPS} on
    RewriteRule .* - [E=WRDFNC_HTTPS:_https]
    RewriteCond %{HTTP:Accept-Encoding} gzip
    RewriteRule .* - [E=WRDFNC_ENC:_gzip]
    RewriteCond %{REQUEST_METHOD} !=POST
    {$sslString}
    RewriteCond %{QUERY_STRING} ^(?:\d+=\d+)?$
    RewriteCond %{REQUEST_URI} (?:\/|\.html)$ [NC]
    RewriteCond %{HTTP_COOKIE} !(comment_author|wp\-postpass|wf_logout|wordpress_logged_in|wptouch_switch_toggle|wpmp_switcher) [NC]
    {$otherRewriteConds}
    RewriteCond %{REQUEST_URI} \/*([^\/]*)\/*([^\/]*)\/*([^\/]*)\/*([^\/]*)\/*([^\/]*)(.*)$
    RewriteCond "%{DOCUMENT_ROOT}{$pathPrefix}/wp-content/wfcache/%{HTTP_HOST}_%1/%2~%3~%4~%5~%6_wfcache%{ENV:WRDFNC_HTTPS}.html%{ENV:WRDFNC_ENC}" -f
    RewriteRule \/*([^\/]*)\/*([^\/]*)\/*([^\/]*)\/*([^\/]*)\/*([^\/]*)(.*)$ "{$pathPrefix}/wp-content/wfcache/%{HTTP_HOST}_{$matchCaps}_wfcache%{ENV:WRDFNC_HTTPS}.html%{ENV:WRDFNC_ENC}" [L]
</IfModule>
#Do not remove this line. Disable Web caching in Wordfence to remove this data - WFCACHECODE
EOT;
        return $code;
    }

    /**
     * @param string $str
     */
    private static function regex_space_fix( $str )
    {
        return str_replace( ' ', '\\s', $str );
    }

    public static function get_htaccess_path()
    {
        if( ! function_exists( 'get_home_path' ) )
        {
            include_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $homePath = get_home_path();
        $htaccessFile = $homePath . '.htaccess';
        return $htaccessFile;
    }
    public static function do_not_cache()
    {
        if( ! defined( 'WFDONOTCACHE' ) )
        {
            define( 'WFDONOTCACHE', true );
        }
    }
}
