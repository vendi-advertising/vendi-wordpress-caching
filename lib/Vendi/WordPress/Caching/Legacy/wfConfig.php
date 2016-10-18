<?php

namespace Vendi\WordPress\Caching\Legacy;

class wfConfig {
    private static $vwc_settings;

    private static function get_vwc_settings()
    {
        if( ! self::$vwc_settings )
        {
            self::$vwc_settings = \Vendi\WordPress\Caching\cache_settings::get_instance( true );
        }

        return self::$vwc_settings;
    }
    public static function set($key, $val, $not_used = null ) {

        switch ( $key )
        {
            case 'cacheType':
                self::get_vwc_settings()->set_cache_mode( $val );
                self::get_vwc_settings()->persist_to_database();
                return;

            case 'allowHTTPSCaching':
                self::get_vwc_settings()->set_do_cache_https_urls( $val );
                self::get_vwc_settings()->persist_to_database();
                return;

            case 'clearCacheSched':
                self::get_vwc_settings()->set_do_clear_on_save( $val );
                self::get_vwc_settings()->persist_to_database();
                return;

            case 'addCacheComment':
                self::get_vwc_settings()->set_do_append_debug_message( $val );
                self::get_vwc_settings()->persist_to_database();
                return;

            case 'cacheExclusions':
                if( ! is_serialized( $val ) )
                {
                    $val = serialize( $val );
                }
                self::get_vwc_settings()->set_cache_exclusions( $val );
                self::get_vwc_settings()->persist_to_database();
                return;
        }

        //TODO: This is for debugging purposes to catch unknown keys
        wp_die( 'Unknown key access in set: ' . $key );
    }
    public static function get($key, $default = false) {
        switch ( $key )
        {
            case 'cacheType':
                return self::get_vwc_settings()->get_cache_mode();

            case 'allowHTTPSCaching':
                return self::get_vwc_settings()->get_do_cache_https_urls();

            case 'clearCacheSched':
                return self::get_vwc_settings()->get_do_clear_on_save();

            case 'addCacheComment':
                return self::get_vwc_settings()->get_do_append_debug_message();

            case 'cacheExclusions':
                $tmp = self::get_vwc_settings()->get_cache_exclusions();
                if( is_serialized( $tmp ) )
                {
                    $tmp = unserialize( $tmp );
                }
                return $tmp;
        }

        //TODO: This is for debugging purposes to catch unknown keys
        wp_die( 'Unknown key access in get: ' . $key );
    }
}
