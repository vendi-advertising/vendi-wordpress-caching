<?php

namespace Vendi\WordPress\Caching;

class cache_settings
{
/*Fields*/
    private $cache_mode;

    private $do_cache_https_urls = false;

    private $do_append_debug_message = false;

    private $do_clear_on_save = false;

    private $cache_exclusions = array();

/*Constants*/
    const CACHE_MODE_OFF = 'off';

    const CACHE_MODE_PHP = 'php';

    const CACHE_MODE_ENHANCED = 'enhanced';

/*Property Access*/
    public function get_cache_mode()
    {
        return $this->cache_mode;
    }

    public function set_cache_mode( $cache_mode )
    {
        switch( $cache_mode )
        {
            case self::CACHE_MODE_OFF:
            case self::CACHE_MODE_PHP:
            case self::CACHE_MODE_ENHANCED:
                $this->cache_mode = $cache_mode;
        }

        throw new cache_setting_exception( __( sprintf( 'Unknown cache mode: %1$s', $cache_mode ), 'Vendi Caching' ) );
    }

    public function get_do_cache_https_urls()
    {
        return $this->do_cache_https_urls;
    }

    public function set_do_cache_https_urls( $do_cache_https_urls )
    {
        $this->do_cache_https_urls = $do_cache_https_urls;
    }

    public function get_do_append_debug_message()
    {
        return $this->do_append_debug_message;
    }

    public function set_do_append_debug_message( $do_append_debug_message )
    {
        $this->do_append_debug_message = $do_append_debug_message;
    }

    public function get_do_clear_on_save()
    {
        return $this->do_clear_on_save;
    }

    public function set_do_clear_on_save( $do_clear_on_save )
    {
        $this->do_clear_on_save = $do_clear_on_save;
    }

    public function get_cache_exclusions()
    {
        return $this->cache_exclusions;
    }

    public function add_single_cache_exclusion( $cache_exclusion )
    {
        if( ! $cache_exclusion )
        {
            throw new cache_setting_exception( __( 'Empty value passed to add_single_cache_exclusion.', 'Vendi Caching' ) );
        }

        if( ! $cache_exclusion instanceof cache_exclusion )
        {
            throw new cache_setting_exception( __( 'Method add_single_cache_exclusion must be provided with type cache_exclusion.', 'Vendi Caching' ) );
        }

        $this->cache_exclusions[] = $cache_exclusion;
    }
}
