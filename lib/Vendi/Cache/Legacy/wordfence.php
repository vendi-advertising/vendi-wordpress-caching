<?php

namespace Vendi\Cache\Legacy;

use Vendi\Cache\cache_settings;
use Vendi\Cache\utils;
use Vendi\Cache\wordpress_actions;

class wordfence
{
    private static $runInstallCalled = false;

    private static $vwc_cache_settings;

    /**
     * @return null|cache_settings
     */
    public static function get_vwc_cache_settings()
    {
        if( ! self::$vwc_cache_settings )
        {
            self::$vwc_cache_settings = new cache_settings();
        }

        return self::$vwc_cache_settings;

    }

    public static function install_plugin()
    {
        self::runInstall();
        //Used by MU code below
        update_option( VENDI_CACHE_OPTION_KEY_FOR_ACTIVATION, 1 );
    }

    public static function uninstall_plugin()
    {
        //Check if caching is enabled and if it is, disable it and fix the .htaccess file.
        $cacheType = self::get_vwc_cache_settings()->get_cache_mode();
        if( $cacheType == cache_settings::CACHE_MODE_ENHANCED )
        {
            wfCache::add_htaccess_code( 'remove' );
            self::get_vwc_cache_settings()->set_cache_mode( cache_settings::CACHE_MODE_OFF );

            //We currently don't clear the cache when plugin is disabled because it will take too long if done synchronously and won't work because plugin is disabled if done asynchronously.
            //TODO: A warning should be issued telling people that they need to manually clear their cache
            //wfCache::schedule_cache_clear();
        }
        else if( $cacheType == cache_settings::CACHE_MODE_PHP )
        {
            self::get_vwc_cache_settings()->set_cache_mode( cache_settings::CACHE_MODE_OFF );
        }

        //Used by MU code below
        update_option( VENDI_CACHE_OPTION_KEY_FOR_ACTIVATION, 0 );

        cache_settings::uninstall();
    }

    public static function runInstall()
    {
        if( self::$runInstallCalled )
        {
            return;
        }

        self::$runInstallCalled = true;
        if( function_exists( 'ignore_user_abort' ) )
        {
            ignore_user_abort( true );
        }
        $previous_version = get_option( VENDI_CACHE_OPTION_KEY_FOR_VERSION, '0.0.0' );
        update_option( VENDI_CACHE_OPTION_KEY_FOR_VERSION, VENDI_CACHE_VERSION ); //In case we have a fatal error we don't want to keep running install.
        //EVERYTHING HERE MUST BE IDEMPOTENT

        if( self::get_vwc_cache_settings()->is_any_cache_mode_enabled() )
        {
            wfCache::remove_cache_directory_htaccess();
        }
    }

    public static function install_actions()
    {
        register_activation_hook( VENDI_CACHE_FCPATH, array( __CLASS__, 'install_plugin' ) );
        register_deactivation_hook( VENDI_CACHE_FCPATH, array( __CLASS__, 'uninstall_plugin' ) );

        $versionInOptions = get_option( VENDI_CACHE_OPTION_KEY_FOR_VERSION, false );
        if( ( ! $versionInOptions ) || version_compare( VENDI_CACHE_VERSION, $versionInOptions, '>' ) )
        {
            //Either there is no version in options or the version in options is greater and we need to run the upgrade
            self::runInstall();
        }

        wfCache::setup_caching();

        if( defined( 'MULTISITE' ) && MULTISITE === true )
        {
            //Because the plugin is active once installed, even before it's network activated, for site 1 (WordPress team, why?!)
            if( 1 === get_current_blog_id() && get_option( VENDI_CACHE_OPTION_KEY_FOR_ACTIVATION ) != 1 )
            {
                return;
            }
        }

        wordpress_actions::install_all_actions( self::get_vwc_cache_settings() );

        if( is_admin() )
        {
            add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
            if( VENDI_CACHE_SUPPORT_MU && is_multisite() )
            {
                if( wfUtils::isAdminPageMU() )
                {
                    add_action( 'network_admin_menu', array( __CLASS__, 'admin_menus' ) );
                }
            }
            else
            {
                add_action( 'admin_menu', array( __CLASS__, 'admin_menus' ) );
            }
        }
    }

    public static function ajax_receiver()
    {
        if( ! wfUtils::isAdmin() )
        {
            die( json_encode( array( 'errorMsg' => esc_html__( 'You appear to have logged out or you are not an admin. Please sign-out and sign-in again.', 'Vendi Cache' ) ) ) );
        }

        //Attempt to get the values from POST, and fallback to GET
        $func = utils::get_post_value( 'action', utils::get_get_value( 'action' ) );
        $nonce = utils::get_post_value( 'nonce', utils::get_get_value( 'nonce' ) );
        if( ! wp_verify_nonce( $nonce, 'wp-ajax' ) )
        {
            die( json_encode( array( 'errorMsg' => esc_html__( 'Your browser sent an invalid security token. Please try reloading this page or signing out and in again.', 'Vendi Cache' ) ) ) );
        }

        $func = str_replace( 'vendi_cache_', '', $func );
        $fq_func = array( __CLASS__, 'ajax_' . $func . '_callback' );
        if( ! is_callable( $fq_func ) )
        {
            die( json_encode( array( 'errorMsg' => sprintf( esc_html__( 'Could not find AJAX func %1$s', 'Vendi Cache' ), esc_html( $func ) ) ) ) );
        }
        $returnArr = call_user_func( $fq_func );
        if( $returnArr === false )
        {
            $returnArr = array( 'errorMsg' => esc_html__( 'We encountered an internal error executing that request.', 'Vendi Cache' ) );
        }

        if( ! is_array( $returnArr ) )
        {
            error_log( sprintf( __( 'Function %1$s did not return an array and did not generate an error.', 'Vendi Cache' ), esc_html( $func ) ) );
            $returnArr = array();
        }
        if( isset( $returnArr[ 'nonce' ] ) )
        {
            error_log( __( 'The ajax function returned an array with \'nonce\' already set. This could be a bug.', 'Vendi Cache' ) );
        }
        $returnArr[ 'nonce' ] = wp_create_nonce( 'wp-ajax' );
        die( json_encode( $returnArr ) );
    }

    public static function ajax_removeFromCache_callback()
    {
        $id = utils::get_post_value( 'id' );
        $link = get_permalink( $id );
        if( preg_match( '/^https?:\/\/([^\/]+)(.*)$/i', $link, $matches ) )
        {
            $host = $matches[ 1 ];
            $URI = $matches[ 2 ];
            if( ! $URI )
            {
                $URI = '/';
            }
            $sslFile = wfCache::file_from_uri( $host, $URI, true ); //SSL
            $normalFile = wfCache::file_from_uri( $host, $URI, false ); //non-SSL
            @unlink( $sslFile );
            @unlink( $sslFile . '_gzip' );
            @unlink( $normalFile );
            @unlink( $normalFile . '_gzip' );
        }
        return array( 'ok' => 1 );
    }

    public static function ajax_saveCacheOptions_callback()
    {
        $changed = false;
        if( utils::get_post_value( 'allowHTTPSCaching' ) != self::get_vwc_cache_settings()->get_do_cache_https_urls() )
        {
            $changed = true;
        }
        self::get_vwc_cache_settings()->set_do_cache_https_urls( utils::get_post_value( 'allowHTTPSCaching' ) == 1 );
        self::get_vwc_cache_settings()->set_do_append_debug_message( utils::get_post_value( 'addCacheComment' ) == 1 );
        self::get_vwc_cache_settings()->set_do_clear_on_save( utils::get_post_value( 'clearCacheSched' ) == 1 );
        if( $changed && self::get_vwc_cache_settings()->get_cache_mode() == cache_settings::CACHE_MODE_ENHANCED )
        {
            $err = wfCache::add_htaccess_code( 'add' );
            if( $err )
            {
                return array(
                        'updateErr' => sprintf(
                            esc_html__( 'Vendi Cache could not edit your .htaccess file. The error was: %1$s', 'Vendi Cache' ),
                            esc_html( $err )
                        ),
                        'code' => wfCache::get_htaccess_code(),
                    );
            }
        }
        wfCache::schedule_cache_clear();
        return array( 'ok' => 1 );
    }

    public static function ajax_saveCacheConfig_callback()
    {
        $cacheType = utils::get_post_value( 'cacheType' );
        if( $cacheType == cache_settings::CACHE_MODE_ENHANCED || $cacheType == cache_settings::CACHE_MODE_PHP )
        {
            $plugins = get_plugins();
            $badPlugins = array();
            foreach( $plugins as $pluginFile => $data )
            {
                if( is_plugin_active( $pluginFile ) )
                {
                    if( $pluginFile == 'w3-total-cache/w3-total-cache.php' )
                    {
                        $badPlugins[ ] = 'W3 Total Cache';
                    }
                    else if( $pluginFile == 'quick-cache/quick-cache.php' )
                    {
                        $badPlugins[ ] = 'Quick Cache';
                    }
                    else if( $pluginFile == 'wp-super-cache/wp-cache.php' )
                    {
                        $badPlugins[ ] = 'WP Super Cache';
                    }
                    else if( $pluginFile == 'wp-fast-cache/wp-fast-cache.php' )
                    {
                        $badPlugins[ ] = 'WP Fast Cache';
                    }
                    else if( $pluginFile == 'wp-fastest-cache/wpFastestCache.php' )
                    {
                        $badPlugins[ ] = 'WP Fastest Cache';
                    }
                }
            }
            if( count( $badPlugins ) > 0 )
            {
                return array(
                                'errorMsg' => sprintf(
                                                        wp_kses(
                                                                    __(
                                                                        'You can not enable Vendi Cache with other caching plugins enabled as this may cause conflicts. The plugins you have that conflict are: <strong>1$s.</strong> Disable these plugins, then return to this page and enable Vendi Cache.',
                                                                        'Vendi Cache'
                                                                        ),
                                                                    array(
                                                                            'strong' => array(),
                                                                    )
                                                            ),
                                                            implode( ', ', $badPlugins )
                                                    )
                            );
            }

            //Make sure that Wordfence caching is not enabled
            if( is_plugin_active( 'wordfence/wordfence.php' ) )
            {
                if( class_exists( '\wfConfig' ) )
                {
                    if( method_exists( '\wfConfig', 'get' ) )
                    {
                        $wf_cacheType = \wfConfig::get( 'cacheType' );
                        if( 'php' == $wf_cacheType || 'falcon' == $wf_cacheType )
                        {
                            return array( 'errorMsg' => esc_html__( 'Please disable WordFence\s cache before enabling Vendi Cache.', 'Vendi Cache' ) );
                        }
                    }
                }
            }

            $siteURL = site_url();
            if( preg_match( '/^https?:\/\/[^\/]+\/[^\/]+\/[^\/]+\/.+/i', $siteURL ) )
            {
                return array( 'errorMsg' => 'Vendi Cache currently does not support sites that are installed in a subdirectory and have a home page that is more than 2 directory levels deep. e.g. we don\'t support sites who\'s home page is http://example.com/levelOne/levelTwo/levelThree' );
            }
        }
        if( $cacheType == cache_settings::CACHE_MODE_ENHANCED )
        {
            if( ! get_option( 'permalink_structure', '' ) )
            {
                return array(
                                'errorMsg' => esc_html__( 'You need to enable Permalinks for your site to use the disk-based cache. You can enable Permalinks in WordPress by going to the Settings - Permalinks menu and enabling it there. Permalinks change your site URL structure from something that looks like /p=123 to pretty URLs like /my-new-post-today/ that are generally more search engine friendly.', 'Vendi Cache' )
                            );
            }
        }
        $warnHtaccess = false;
        if( $cacheType == cache_settings::CACHE_MODE_OFF || $cacheType == cache_settings::CACHE_MODE_PHP )
        {
            $removeError = wfCache::add_htaccess_code( 'remove' );
            if( $removeError || $removeError2 )
            {
                $warnHtaccess = true;
            }
        }
        if( $cacheType == cache_settings::CACHE_MODE_PHP || $cacheType == cache_settings::CACHE_MODE_ENHANCED )
        {
            $cache_dir_name_safe = self::get_vwc_cache_settings()->get_cache_folder_name_safe();

            $err = wfCache::cache_directory_test();
            if( $err )
            {
                return array(
                                'ok' => 1,
                                'heading' => 'Could not write to cache directory',
                                'body' => sprintf(
                                                    esc_html__( 'To enable caching, %1$s needs to be able to create and write to the %2$s directory. We did some tests that indicate this is not possible. You need to manually create the %2$s directory and make it writable by %1$s. The error we encountered was during our tests was: %3$s', 'Vendi Cache' ),
                                                    VENDI_CACHE_PLUGIN_NAME,
                                                    "/wp-content/{$cache_dir_name_safe}/",
                                                    esc_html( $err )
                                                )

                            );
            }
        }

        //Mainly we clear the cache here so that any footer cache diagnostic comments are rebuilt. We could just leave it intact unless caching is being disabled.
        if( $cacheType != self::get_vwc_cache_settings()->get_cache_mode() )
        {
            wfCache::schedule_cache_clear();
        }
        $htMsg = "";
        if( $warnHtaccess )
        {
            $htMsg = ' <strong style="color: #F00;">' . esc_html__( 'Warning: We could not remove the caching code from your .htaccess file. You will need to manually remove this file.', 'Vendi Cache' ) . '</strong> ';
        }
        if( $cacheType == cache_settings::CACHE_MODE_OFF )
        {
            self::get_vwc_cache_settings()->set_cache_mode( cache_settings::CACHE_MODE_OFF );
            return array( 'ok' => 1, 'heading' => esc_html__( 'Caching successfully disabled.', 'Vendi Cache' ), 'body' => "{$htMsg} Caching has been disabled on your system.<br /><br /><center><input type='button' name='wfReload' value='Click here now to refresh this page' onclick='window.location.reload(true);' /></center>" );
        }
        else if( $cacheType == cache_settings::CACHE_MODE_PHP )
        {
            self::get_vwc_cache_settings()->set_cache_mode( cache_settings::CACHE_MODE_PHP );
            return array( 'ok' => 1, 'heading' => esc_html__( 'Basic Caching Enabled', 'Vendi Cache' ), 'body' => "{$htMsg} Basic caching has been enabled on your system.<br /><br /><center><input type='button' name='wfReload' value='Click here now to refresh this page' onclick='window.location.reload(true);' /></center>" );
        }
        else if( $cacheType == cache_settings::CACHE_MODE_ENHANCED )
        {
            if( utils::get_post_value( 'noEditHtaccess' ) != '1' )
            {
                $err = wfCache::add_htaccess_code( 'add' );
                if( $err )
                {
                    return array(
                                    'ok' => 1,
                                    'heading' => sprintf( esc_html__( '%1$s could not edit .htaccess', 'Vendi Cache' ), VENDI_CACHE_PLUGIN_NAME ),
                                    'body' => "Vendi Cache could not edit your .htaccess code. The error was: " . $err
                                );
                }
            }
            self::get_vwc_cache_settings()->set_cache_mode( cache_settings::CACHE_MODE_ENHANCED );
            // wfCache::scheduleUpdateBlockedIPs(); //Runs every 5 mins until we change cachetype
            return array(
                            'ok'        => 1,
                            'heading'   => esc_html__( 'Disk-based cache activated!', 'Vendi Cache' ),
                            'body'      => esc_html__( 'Disk-based cache has been activated on your system.', 'Vendi Cache' ) .
                                           ' <center><input type="button" name="wfReload" value="' . 
                                           esc_attr_x( 'Click here now to refresh this page', 'Vendi Cache' ) . 
                                           '" onclick="window.location.reload(true);"" /></center>' );
        }
        return array(
                        'errorMsg' => sprintf( esc_html__( 'An error occurred. Probably an unknown cacheType: %1$s', 'Vendi Cache' ), esc_html( $cacheType ) )
                    );
    }

    public static function ajax_getCacheStats_callback()
    {
        $s = wfCache::get_cache_stats();
        if( $s[ 'files' ] == 0 )
        {
            return array( 'ok' => 1, 'heading' => 'Cache Stats', 'body' => "The cache is currently empty. It may be disabled or it may have been recently cleared." );
        }
        $body = 'Total files in cache: ' . $s[ 'files' ] .
            '<br />Total directories in cache: ' . $s[ 'dirs' ] .
            '<br />Total data: ' . $s[ 'data' ] . 'KB';
        if( $s[ 'compressedFiles' ] > 0 )
        {
            $body .= '<br />Files: ' . $s[ 'uncompressedFiles' ] .
                '<br />Data: ' . $s[ 'uncompressedKBytes' ] . 'KB' .
                '<br />Compressed files: ' . $s[ 'compressedFiles' ] .
                '<br />Compressed data: ' . $s[ 'compressedKBytes' ] . 'KB';
        }
        if( $s[ 'largestFile' ] > 0 )
        {
            $body .= '<br />Largest file: ' . $s[ 'largestFile' ] . 'KB';
        }
        if( $s[ 'oldestFile' ] !== false )
        {
            $body .= '<br />Oldest file in cache created ';
            if( time() - $s[ 'oldestFile' ] < 300 )
            {
                $body .= ( time() - $s[ 'oldestFile' ] ) . ' seconds ago';
            }
            else
            {
                $body .= human_time_diff( $s[ 'oldestFile' ] ) . ' ago.';
            }
        }
        if( $s[ 'newestFile' ] !== false )
        {
            $body .= '<br />Newest file in cache created ';
            if( time() - $s[ 'newestFile' ] < 300 )
            {
                $body .= ( time() - $s[ 'newestFile' ] ) . ' seconds ago';
            }
            else
            {
                $body .= human_time_diff( $s[ 'newestFile' ] ) . ' ago.';
            }
        }

        return array( 'ok' => 1, 'heading' => 'Cache Stats', 'body' => $body );
    }

    public static function ajax_clearPageCache_callback()
    {
        $cache_dir_name_safe = self::get_vwc_cache_settings()->get_cache_folder_name_safe();
        $stats = wfCache::clear_page_cache();
        if( $stats[ 'error' ] )
        {
            $body = "A total of " . $stats[ 'totalErrors' ] . " errors occurred while trying to clear your cache. The last error was: " . $stats[ 'error' ];
            return array( 'ok' => 1, 'heading' => 'Error occurred while clearing cache', 'body' => $body );
        }
        $body = "A total of " . $stats[ 'filesDeleted' ] . ' files were deleted and ' . $stats[ 'dirsDeleted' ] . ' directories were removed. We cleared a total of ' . $stats[ 'totalData' ] . 'KB of data in the cache.';
        if( $stats[ 'totalErrors' ] > 0 )
        {
            $body .= ' A total of ' . $stats[ 'totalErrors' ] . ' errors were encountered. This probably means that we could not remove some of the files or directories in the cache. Please use your CPanel or file manager to remove the rest of the files in the directory: ' . WP_CONTENT_DIR . '/' . $cache_dir_name_safe . '/';
        }
        return array( 'ok' => 1, 'heading' => 'Page Cache Cleared', 'body' => $body );
    }

    public static function ajax_checkFalconHtaccess_callback()
    {
        if( wfUtils::isNginx() )
        {
            return array( 'nginx' => 1 );
        }
        $file = wfCache::get_htaccess_path();
        if( ! $file )
        {
            return array( 'err' => "We could not find your .htaccess file to modify it.", 'code' => wfCache::get_htaccess_code() );
        }
        $fh = @fopen( $file, 'r+' );
        if( ! $fh )
        {
            $err = error_get_last();
            return array( 'err' => "We found your .htaccess file but could not open it for writing: " . $err[ 'message' ], 'code' => wfCache::get_htaccess_code() );
        }
        return array( 'ok' => 1 );
    }

    public static function ajax_downloadHtaccess_callback()
    {
        $url = site_url();
        $url = preg_replace( '/^https?:\/\//i', '', $url );
        $url = preg_replace( '/[^a-zA-Z0-9\.]+/', '_', $url );
        $url = preg_replace( '/^_+/', '', $url );
        $url = preg_replace( '/_+$/', '', $url );
        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: attachment; filename="htaccess_Backup_for_' . $url . '.txt"' );
        $file = wfCache::get_htaccess_path();
        readfile( $file );
        die();
    }

    public static function ajax_addCacheExclusion_callback()
    {
        $ex = self::get_vwc_cache_settings()->get_cache_exclusions();
        $ex[ ] = array(
            'pt' => utils::get_post_value( 'patternType' ),
            'p' => utils::get_post_value( 'pattern' ),
            'id' => microtime( true ),
            );

        self::get_vwc_cache_settings()->set_cache_exclusions( $ex );
        wfCache::schedule_cache_clear();
        if( self::get_vwc_cache_settings()->get_cache_mode() == cache_settings::CACHE_MODE_ENHANCED && preg_match( '/^(?:uac|uaeq|cc)$/', utils::get_post_value( 'patternType' ) ) )
        {
            //rewrites htaccess rules
            if( wfCache::add_htaccess_code( 'add' ) )
            {
                return array( 'errorMsg' => "We added the rule you requested but could not modify your .htaccess file. Please delete this rule, check the permissions on your .htaccess file and then try again." );
            }
        }
        return array( 'ok' => 1 );
    }

    public static function ajax_removeCacheExclusion_callback()
    {
        $id = utils::get_post_value( 'id' );
        $ex = self::get_vwc_cache_settings()->get_cache_exclusions();
        if( ! $ex || 0 === count( $ex ) )
        {
            return array( 'ok' => 1 );
        }
        $rewriteHtaccess = false;
        for( $i = 0; $i < sizeof( $ex ); $i++ )
        {
            if( (string)$ex[ $i ][ 'id' ] == (string)$id )
            {
                if( self::get_vwc_cache_settings()->get_cache_mode() == cache_settings::CACHE_MODE_ENHANCED && preg_match( '/^(?:uac|uaeq|cc)$/', $ex[ $i ][ 'pt' ] ) )
                {
                    $rewriteHtaccess = true;
                }
                array_splice( $ex, $i, 1 );
                //Dont break in case of dups
            }
        }
        self::get_vwc_cache_settings()->set_cache_exclusions( $ex );
        if( $rewriteHtaccess && wfCache::add_htaccess_code( 'add' ) )
        {
            //rewrites htaccess rules
            return array( 'errorMsg', "We removed that rule but could not rewrite your .htaccess file. You're going to have to manually remove this rule from your .htaccess file. Please reload this page now." );
        }
        return array( 'ok' => 1 );
    }

    public static function ajax_loadCacheExclusions_callback()
    {
        $ex = self::get_vwc_cache_settings()->get_cache_exclusions();
        if( ! $ex || 0 === count( $ex ) )
        {
            return array( 'ex' => false );
        }
        return array( 'ex' => $ex );
    }

    public static function admin_init()
    {
        if( ! wfUtils::isAdmin() )
        {
            return;
        }

        $ajaxEndpoints = array(
                                'removeExclusion', 'downloadHtaccess', 'checkFalconHtaccess',
                                'saveCacheConfig', 'removeFromCache', 'saveCacheOptions', 'clearPageCache', 'getCacheStats',
                                'addCacheExclusion', 'removeCacheExclusion', 'loadCacheExclusions',
                            );

        foreach( $ajaxEndpoints as $func )
        {
            add_action( 'wp_ajax_vendi_cache_' . $func, array( __CLASS__, 'ajax_receiver' ) );
        }

        if( VENDI_CACHE_PLUGIN_PAGE_SLUG === utils::get_get_value( 'page' ) )
        {
            wp_enqueue_style( 'vendi-cache-main-style', wfUtils::getBaseURL() . 'css/main.css', '', VENDI_CACHE_VERSION );
            wp_enqueue_style( 'vendi-cache-colorbox-style', wfUtils::getBaseURL() . 'css/colorbox.css', '', VENDI_CACHE_VERSION );

            wp_enqueue_script( 'json2' );
            wp_enqueue_script( 'jquery.wftmpl', wfUtils::getBaseURL() . 'js/jquery.tmpl.min.js', array( 'jquery' ), VENDI_CACHE_VERSION );
            wp_enqueue_script( 'jquery.wfcolorbox', wfUtils::getBaseURL() . 'js/jquery.colorbox-min.js', array( 'jquery' ), VENDI_CACHE_VERSION );

            wp_enqueue_script( 'vendi-cache-admin', wfUtils::getBaseURL() . 'js/admin.js', array( 'jquery' ), VENDI_CACHE_VERSION );
            wp_enqueue_script( 'vendi-cache-admin-extra', wfUtils::getBaseURL() . 'js/admin-inner.js', array( 'jquery' ), VENDI_CACHE_VERSION );
        }
        else
        {
            wp_enqueue_script( 'vendi-cache-admin', wfUtils::getBaseURL() . 'js/admin-inner.js', array( 'jquery' ), VENDI_CACHE_VERSION );
        }
        self::setupAdminVars();
    }

    private static function setupAdminVars()
    {
        //Translators... I'm sorry.
        $nonce = wp_create_nonce( 'wp-ajax' );
        wp_localize_script(
                            'vendi-cache-admin',
                            'VendiCacheAdminVars', array(
                                                            'ajaxURL' => admin_url( 'admin-ajax.php' ),
                                                            'firstNonce' => $nonce,
                                                            'cacheType' => self::get_vwc_cache_settings()->get_cache_mode(),

                                                            'msg_loading' => sprintf( esc_html__( '%1$s is working...', 'Vendi Cache' ), VENDI_CACHE_PLUGIN_NAME ),
                                                            'msg_general_error' => esc_html__( 'An error occurred', 'Vendi Cache' ),

                                                            'msg_heading_enable_enhanced' => esc_html__( 'Enabling disk-based cache', 'Vendi Cache' ),
                                                            'msg_heading_error' => esc_html__( 'We encountered a problem', 'Vendi Cache' ),
                                                            'msg_heading_invalid_pattern' => esc_html__( 'Incorrect pattern for exclusion', 'Vendi Cache' ),
                                                            'msg_heading_cache_exclusions' => esc_html__( 'Cache Exclusions', 'Vendi Cache' ),
                                                            'msg_heading_manual_update' => esc_html__( 'You need to manually update your .htaccess', 'Vendi Cache' ),

                                                            'msg_switch_apache' => esc_html__( 'The disk-based cache modifies your website configuration file which is called your .htaccess file. To enable the disk-based cache we ask that you make a backup of this file. This is a safety precaution in case for some reason the disk-based cache is not compatible with your site.', 'Vendi Cache' ) .
                                                                                    '<br /><br /><a href="' . admin_url( 'admin-ajax.php' ) . '?action=vendi_cache_downloadHtaccess&amp;nonce=' . $nonce . '" onclick="jQuery(\'#wfNextBut\').prop(\'disabled\', false); return true;">' .
                                                                                    esc_html__( 'Click here to download a backup copy of your .htaccess file now', 'Vendi Cache' ) .
                                                                                    '</a><br /><br /> <input type="button" name="but1" id="wfNextBut" value="' .
                                                                                    esc_attr_x( 'Click to enable the disk-based cache' ,'Vendi Cache' ) .
                                                                                    '" disabled="disabled" onclick="VCAD.confirmSwitchToFalcon(0);" />',
                                                            'msg_switch_nginx'  => sprintf(
                                                                                            wp_kses(
                                                                                                        __( 'You are using an Nginx web server and using a FastCGI processor like PHP5-FPM. To use the disk-based cache you will need to manually modify your nginx.conf configuration file and reload your Nginx server for the changes to take effect. You can find the <a href="%1$s" target="_blank">rules you need to make these changes to nginx.conf on this page on wordfence.com</a>. Once you have made these changes, compressed cached files will be served to your visitors directly from Nginx making your site extremely fast. When you have made the changes and reloaded your Nginx server, you can click the button below to enable the disk-based cache.', 'Vendi Cache' ),
                                                                                                        array(
                                                                                                                'a' => array(
                                                                                                                                'href' => array(),
                                                                                                                                'target' => array(),
                                                                                                                            ),
                                                                                                        )
                                                                                                ),
                                                                                            esc_url( 'http://www.wordfence.com/blog/2014/05/nginx-wordfence-falcon-engine-php-fpm-fastcgi-fast-cgi/' )
                                                                                            ) .
                                                                                    '<br /><br /><input type="button" name="but1" id="wfNextBut" value="' .
                                                                                    esc_attr_x( 'Click to enable the disk-based cache' ,'Vendi Cache' ) .
                                                                                    '" onclick="VCAD.confirmSwitchToFalcon(1);" />',

                                                            'msg_switch_error'  => esc_html__( 'We can\'t modify your .htaccess file for you because: @@1@@', 'Vendi Cache' ) .
                                                                                    '<br /><br />' .
                                                                                    esc_html__( 'Advanced users: If you would like to manually enable the disk-based cache yourself by editing your .htaccess, you can add the rules below to the beginning of your .htaccess file. Then click the button below to enable %1$s. Don\'t do this unless you understand website configuration.', 'Vendi Cache' ) .
                                                                                    '<br /><textarea style="width: 300px; height:100px;" readonly>@@2@@</textarea><br /><input type="button" value="' . 
                                                                                    esc_attr_x( 'Enable the disk-based cache after manually editing .htaccess', 'Vendi Cache' ) .
                                                                                    '" onclick="VCAD.confirmSwitchToFalcon(1);" />',

                                                            'msg_manual_update' => '@@1@@<br />' . 
                                                                                    esc_html__( 'Your option was updated but you need to change the disk-based cache code in your .htaccess to the following:', 'Vendi Cache' ) .
                                                                                    '<br /><textarea style="width: 300px; height: 120px;">@@2@@</textarea>',

                                                            'msg_invalid_pattern' => esc_html__( 'You can not enter full URL\'s for exclusion from caching. You entered a full URL that started with http:// or https://. You must enter relative URL\'s e.g. /exclude/this/page/. You can also enter text that might be contained in the path part of a URL or at the end of the path part of a URL.', 'Vendi Cache' ) ,

                                                            'msg_no_exclusions' => esc_html__( 'There are not currently any exclusions. If you have a site that does not change often, it is perfectly normal to not have any pages you want to exclude from the cache.', 'Vendi Cache' ),
                                                        )
                        );
    }

    public static function activation_warning()
    {
        $activationError = get_option( VENDI_CACHE_OPTION_KEY_ACTIVATION_ERROR, '' );
        if( strlen( $activationError ) > 400 )
        {
            $activationError = substr( $activationError, 0, 400 ) . '...[output truncated]';
        }
        if( $activationError )
        {
            echo '<div class="updated fade"><p><strong>' . esc_html__( 'Vendi Cache generated an error on activation. The output we received during activation was:', 'Vendi Cache' ) . '</strong> ' . wp_kses( $activationError, array() ) . '</p></div>';
        }
        delete_option( 'wf_plugin_act_error' );
    }

    public static function admin_menus()
    {
        if( ! wfUtils::isAdmin() )
        {
            return;
        }

        $warningAdded = false;
        if( get_option( VENDI_CACHE_OPTION_KEY_ACTIVATION_ERROR, false ) )
        {
            if( wfUtils::isAdminPageMU() )
            {
                add_action( 'network_admin_notices', array( __CLASS__, 'activation_warning' ) );
            }
            else
            {
                add_action( 'admin_notices', array( __CLASS__, 'activation_warning' ) );
            }
            $warningAdded = true;
        }

        add_submenu_page( 'options-general.php', 'Vendi Cache', 'Vendi Cache', 'activate_plugins', VENDI_CACHE_PLUGIN_PAGE_SLUG, array( __CLASS__, 'show_admin_page' ) );
    }

    public static function show_admin_page()
    {
        require VENDI_CACHE_PATH . '/admin/vendi-cache.php';
    }

    /**
     * Call this to prevent us from caching the current page.
     * @return boolean
     */
    public static function do_not_cache()
    {
        wfCache::do_not_cache();
        return true;
    }

    /**
     * @param string $adminURL
     * @param string $homePath
     * @param bool $relaxedFileOwnership
     * @param bool $output
     * @return bool
     */
    public static function requestFilesystemCredentials( $adminURL, $homePath = null, $relaxedFileOwnership = true, $output = true )
    {
        if( $homePath === null )
        {
            $homePath = get_home_path();
        }

        global $wp_filesystem;

        //TODO: This is very ugly
        ! $output && ob_start();
        if( false === ( $credentials = request_filesystem_credentials( $adminURL, '', false, $homePath, array( 'version', 'locale' ), $relaxedFileOwnership ) ) )
        {
            ! $output && ob_end_clean();
            return false;
        }

        if( ! WP_Filesystem( $credentials, $homePath, $relaxedFileOwnership ) )
        {
            // Failed to connect, Error and request again
            request_filesystem_credentials( $adminURL, '', true, ABSPATH, array( 'version', 'locale' ), $relaxedFileOwnership );
            ! $output && ob_end_clean();
            return false;
        }

        if( $wp_filesystem->errors->get_error_code() )
        {
            ! $output && ob_end_clean();
            return false;
        }
        ! $output && ob_end_clean();
        return true;
    }
}
