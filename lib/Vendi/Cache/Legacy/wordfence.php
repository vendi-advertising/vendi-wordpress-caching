<?php

namespace Vendi\Cache\Legacy;

use Vendi\Cache\cache_settings;
use Vendi\Cache\wordpress_actions;
use Vendi\Cache\AjaxCallbacks;
use Vendi\Shared\utils;

class wordfence
{
    private static $runInstallCalled = false;

    /**
     * @return null|cache_settings
     */
    public static function get_vwc_cache_settings()
    {
        return cache_settings::get_instance();
    }

    public static function install_plugin()
    {
        self::runInstall();
        //Used by MU code below
        cache_settings::get_instance()->update_option_for_instance( VENDI_CACHE_OPTION_KEY_FOR_ACTIVATION, 1 );
    }

    public static function uninstall_plugin()
    {
        //Check if caching is enabled and if it is, disable it and fix the .htaccess file.
        $cacheType = self::get_vwc_cache_settings()->get_cache_mode();
        if( $cacheType == cache_settings::CACHE_MODE_ENHANCED )
        {
            wfCache::add_htaccess_code( 'remove' );

            //This isn't needed anymore, uninstall should take care of it
            self::get_vwc_cache_settings()->set_cache_mode( cache_settings::CACHE_MODE_OFF );

            //We currently don't clear the cache when plugin is disabled because it will take too long if done synchronously and won't work because plugin is disabled if done asynchronously.
            //TODO: A warning should be issued telling people that they need to manually clear their cache
            //wfCache::schedule_cache_clear();
        }
        else if( $cacheType == cache_settings::CACHE_MODE_PHP )
        {

            //This isn't needed anymore, uninstall should take care of it
            self::get_vwc_cache_settings()->set_cache_mode( cache_settings::CACHE_MODE_OFF );
        }

        //Used by MU code below
        cache_settings::get_instance()->update_option_for_instance( VENDI_CACHE_OPTION_KEY_FOR_ACTIVATION, 0 );

        cache_settings::get_instance()->uninstall();
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
        $previous_version = cache_settings::get_instance()->get_option_for_instance( VENDI_CACHE_OPTION_KEY_FOR_VERSION, '0.0.0' );
        cache_settings::get_instance()->update_option_for_instance( VENDI_CACHE_OPTION_KEY_FOR_VERSION, VENDI_CACHE_VERSION ); //In case we have a fatal error we don't want to keep running install.
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

        $versionInOptions = cache_settings::get_instance()->get_option_for_instance( VENDI_CACHE_OPTION_KEY_FOR_VERSION, false );
        if( ( ! $versionInOptions ) || version_compare( VENDI_CACHE_VERSION, $versionInOptions, '>' ) )
        {
            //Either there is no version in options or the version in options is greater and we need to run the upgrade
            self::runInstall();
        }

        wfCache::setup_caching();

        if( defined( 'MULTISITE' ) && MULTISITE === true )
        {
            //Because the plugin is active once installed, even before it's network activated, for site 1 (WordPress team, why?!)
            if( 1 === get_current_blog_id() && cache_settings::get_instance()->get_option_for_instance( VENDI_CACHE_OPTION_KEY_FOR_ACTIVATION ) != 1 )
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
            die( json_encode( new ajax_error( esc_html__( 'You appear to have logged out or you are not an admin. Please sign-out and sign-in again.', 'Vendi Cache' ) ) ) );
        }

        //Attempt to get the values from POST, and fallback to GET
        $func = utils::get_post_value( 'action', utils::get_get_value( 'action' ) );
        $nonce = utils::get_post_value( 'nonce', utils::get_get_value( 'nonce' ) );
        if( ! wp_verify_nonce( $nonce, 'wp-ajax' ) )
        {
            die( json_encode( new ajax_error( esc_html__( 'Your browser sent an invalid security token. Please try reloading this page or signing out and in again.', 'Vendi Cache' ) ) ) );
        }

        $func = str_replace( 'vendi_cache_', '', $func );

        $new_funcs = array(
                            'saveCacheOptions'      => 'save_cache_options',
                            'saveCacheConfig'       => 'save_cache_config',
                            'getCacheStats'         => 'get_cache_stats',
                            'addCacheExclusion'     => 'add_cache_exclusion',
                            'removeCacheExclusion'  => 'remove_cache_exclusion',
                            'loadCacheExclusions'   => 'load_cache_exclusions',
                            'clearPageCache'        => 'clear_page_cache',
                            'removeFromCache'       => 'remove_from_cache',
                            'checkFalconHtaccess'   => 'check_falcon_htaccess',
                            'downloadHtaccess'      => 'download_htaccess',
                        );

        if( array_key_exists( $func, $new_funcs ) )
        {
            $to_call = '\\Vendi\\Cache\\AjaxCallbacks\\' . $new_funcs[ $func ];
            $obj = new $to_call( self::get_vwc_cache_settings() );
            $returnArr = $obj->get_result();
        }
        else
        {
            die( json_encode( new ajax_error( sprintf( esc_html__( 'Could not find AJAX func %1$s', 'Vendi Cache' ), esc_html( $func ) ) ) ) );
        }

        if( $returnArr instanceof \Vendi\Cache\ajax_message || $returnArr instanceof \Vendi\Cache\ajax_error )
        {
            //NOOP
        }
        elseif( is_array( $returnArr ) )
        {
            $returnArr = \Vendi\Cache\ajax_message::create_from_legacy_array( $returnArr );
        }
        else
        {
            error_log( sprintf( __( 'Function %1$s did not return an array or ajax_message and did not generate an error.', 'Vendi Cache' ), esc_html( $func ) ) );
            die( json_encode( array() ) );
        }

        if( $returnArr instanceof \Vendi\Cache\ajax_message )
        {
            if( $returnArr->get_nonce() )
            {
                error_log( __( 'The ajax function returned an object with \'nonce\' already set. This could be a bug.', 'Vendi Cache' ) );
            }

            $returnArr->set_nonce( wp_create_nonce( 'wp-ajax' ) );
        }

        die( json_encode( $returnArr ) );
    }

    public static function admin_init()
    {
        if( ! wfUtils::isAdmin() )
        {
            return;
        }

        $ajaxEndpoints = array(
                                // 'removeExclusion'
                                'downloadHtaccess', 'checkFalconHtaccess',
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
        $activationError = cache_settings::get_instance()->get_option_for_instance( VENDI_CACHE_OPTION_KEY_ACTIVATION_ERROR, '' );
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
        if( cache_settings::get_instance()->get_option_for_instance( VENDI_CACHE_OPTION_KEY_ACTIVATION_ERROR, false ) )
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
     *
     * @deprecated 1.2.0 Use filter \Vendi\Cache\api::FILTER_NAME_DO_NOT_CACHE instead.
     * @return boolean
     */
    public static function do_not_cache()
    {
        return \Vendi\Cache\api::do_not_cache();
    }

}
