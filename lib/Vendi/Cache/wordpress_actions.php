<?php

namespace Vendi\Cache;

use Vendi\Cache\Legacy\wfCache;
use Vendi\Cache\Legacy\wfUtils;
use Vendi\Cache\cache_settings;
use Vendi\Cache\utils;

class wordpress_actions
{
    private static $vwc_cache_settings;

    public static function get_vwc_cache_settings()
    {
        if( ! self::$vwc_cache_settings )
        {
            self::$vwc_cache_settings = new cache_settings();
        }

        return self::$vwc_cache_settings;

    }

    public static function install_all_actions( cache_settings $vwc_cache_settings = null )
    {
        self::$vwc_cache_settings = $vwc_cache_settings;

        add_action( 'publish_future_post', array( __CLASS__, 'publish_future_post' ) );
        add_action( 'mobile_setup', array( __CLASS__, 'jetpack_mobile_setup' ) ); //Action called in Jetpack Mobile Theme: modules/minileven/minileven.php

        if( is_admin() )
        {
            add_filter( 'pre_update_option_permalink_structure', array( __CLASS__, 'disable_permalinks_filter' ), 10, 2 );

            if( cache_settings::CACHE_MODE_ENHANCED === self::get_vwc_cache_settings()->get_cache_mode() || cache_settings::CACHE_MODE_PHP === self::get_vwc_cache_settings()->get_cache_mode() )
            {
                add_filter( 'post_row_actions', array( __CLASS__, 'post_row_actions' ), 0, 2 );
                add_filter( 'page_row_actions', array( __CLASS__, 'page_row_actions' ), 0, 2 );
                add_action( 'post_submitbox_start', array( __CLASS__, 'post_submitbox_start' ) );
            }
        }
    }

    /**
     * @vendi_flag  KEEP
     */
    public static function publish_future_post( $id )
    {
        if( self::get_vwc_cache_settings()->get_do_clear_on_save() )
        {
            wfCache::schedule_cache_clear();
        }
    }

    /**
     * @vendi_flag  KEEP
     */
    public static function jetpack_mobile_setup()
    {
        define( 'WFDONOTCACHE', true ); //Don't cache jetpack mobile theme pages.
    }

    /**
     * @vendi_flag  KEEP
     */
    public static function disable_permalinks_filter( $newVal, $oldVal )
    {
        //Disk-case is enabled and admin is disabling permalinks
        if( self::get_vwc_cache_settings()->get_cache_mode() == cache_settings::CACHE_MODE_ENHANCED && $oldVal && ( ! $newVal ) )
        {
            wfCache::add_htaccess_code( 'remove' );
            self::get_vwc_cache_settings()->set_cache_mode( cache_settings::CACHE_MODE_OFF );
        }
        return $newVal;
    }

    /**
     * @vendi_flag  KEEP
     */
    public static function post_row_actions( $actions, $post )
    {
        if( wfUtils::isAdmin() )
        {
            $actions = array_merge(
                                    $actions,
                                    array(
                                        'wfCachePurge' => '<a href="#" onclick="wordfenceExt.removeFromCache(\'' . $post->ID . '\'); return false;">' . esc_html_x( 'Remove from Vendi Cache', 'Vendi Cache' ) . '</a>'
                                    )
                                );
        }
        return $actions;
    }

    /**
     * @vendi_flag  KEEP
     */
    public static function page_row_actions( $actions, $post )
    {
        if( wfUtils::isAdmin() )
        {
            $actions = array_merge(
                                    $actions,
                                    array(
                                        'wfCachePurge' => '<a href="#" onclick="wordfenceExt.removeFromCache(\'' . $post->ID . '\'); return false;">' . esc_html_x( 'Remove from Vendi Cache', 'Vendi Cache' ) . '</a>'
                                    )
                                );
        }
        return $actions;
    }

    /**
     * @vendi_flag  KEEP
     */
    public static function post_submitbox_start()
    {
        if( wfUtils::isAdmin() )
        {
            $post = get_post();
            echo '<div><a href="#" onclick="wordfenceExt.removeFromCache(\'' . $post->ID . '\'); return false;">' . esc_html_x( 'Remove from Vendi Cache', 'Vendi Cache' ) . '</a></div>';
        }
    }
}
