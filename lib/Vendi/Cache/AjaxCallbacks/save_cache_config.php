<?php

namespace Vendi\Cache\AjaxCallbacks;

use Vendi\Cache\cache_settings;
use Vendi\Cache\ajax_message;
use Vendi\Cache\ajax_error;
use Vendi\Cache\Legacy\wfCache;
use Vendi\Shared\utils;

class save_cache_config extends ajax_callback_base
{
    public function get_result()
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
                return new ajax_error(  sprintf(
                                                        wp_kses(
                                                                    __(
                                                                        'You can not enable Vendi Cache with other caching plugins enabled as this may cause conflicts. The plugins you have that conflict are: <strong>%1$s.</strong> Disable these plugins, then return to this page and enable Vendi Cache.',
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
                            return new ajax_error( esc_html__( 'Please disable WordFence\'s cache before enabling Vendi Cache.', 'Vendi Cache' ) );
                        }
                    }
                }
            }

            $siteURL = site_url();
            if( preg_match( '/^https?:\/\/[^\/]+\/[^\/]+\/[^\/]+\/.+/i', $siteURL ) )
            {
                return new ajax_error( esc_html__( 'Vendi Cache currently does not support sites that are installed in a subdirectory and have a home page that is more than 2 directory levels deep. e.g. we don\'t support sites who\'s home page is http://example.com/levelOne/levelTwo/levelThree', 'Vendi Cache' ) );
            }
        }

        if( $cacheType == cache_settings::CACHE_MODE_ENHANCED )
        {
            if( ! get_option( 'permalink_structure', '' ) )
            {
                return new ajax_error( esc_html__( 'You need to enable Permalinks for your site to use the disk-based cache. You can enable Permalinks in WordPress by going to the Settings - Permalinks menu and enabling it there. Permalinks change your site URL structure from something that looks like /p=123 to pretty URLs like /my-new-post-today/ that are generally more search engine friendly.', 'Vendi Cache' ) );
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
            if( $err instanceof \Exception )
            {
                return new ajax_message(
                                        __( 'Could not write to cache directory', 'Vendi Cache' ),
                                        sprintf(
                                                    esc_html__( 'To enable caching, %1$s needs to be able to create and write to the %2$s directory. We did some tests that indicate this is not possible. You need to manually create the %2$s directory and make it writable by %1$s. The error we encountered was during our tests was: %3$s', 'Vendi Cache' ),
                                                    '<strong>' . VENDI_CACHE_PLUGIN_NAME . '</strong>',
                                                    "/wp-content/{$cache_dir_name_safe}/",
                                                    esc_html( $err->__toString() )
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
            $htMsg = ' <strong style="color: #F00;">' . esc_html__( 'Warning: We could not remove the caching code from your .htaccess file. You will need to manually remove this file.', 'Vendi Cache' ) . '</strong><br /><br />';
        }
        if( $cacheType == cache_settings::CACHE_MODE_OFF )
        {
            self::get_vwc_cache_settings()->set_cache_mode( cache_settings::CACHE_MODE_OFF );
            return new ajax_message(
                    esc_html__( 'Caching successfully disabled.', 'Vendi Cache' ),
                    $htMsg . esc_html__( 'Caching has been disabled on your system.', 'Vendi Cache' ) .
                        '<br /><br /><center><input type="button" name="wfReload" value="' .
                        esc_attr__( 'Click here now to refresh this page', 'Vendi Cache' ) .
                        '" onclick="window.location.reload(true);" /></center>'
                );
        }
        else if( $cacheType == cache_settings::CACHE_MODE_PHP )
        {
            self::get_vwc_cache_settings()->set_cache_mode( cache_settings::CACHE_MODE_PHP );
            return new ajax_message(
                    esc_html__( 'Basic Caching Enabled', 'Vendi Cache' ),
                    $htMsg . esc_html__( 'Basic caching has been enabled on your system.', 'Vendi Cache' ) .
                        '<br /><br /><center><input type="button" name="wfReload" value="' .
                        esc_attr__( 'Click here now to refresh this page', 'Vendi Cache' ) .
                        '" onclick="window.location.reload(true);" /></center>'
                );
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
        return new ajax_error( sprintf( esc_html__( 'An error occurred. Probably an unknown cacheType: %1$s', 'Vendi Cache' ), esc_html( $cacheType ) ) );
    }
}
