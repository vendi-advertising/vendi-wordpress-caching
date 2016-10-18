<?php

namespace Vendi\WordPress\Caching;

class cache_settings
{
/*Fields*/
    private $cache_mode = self::DEFAULT_VALUE_CACHE_MODE;

    private $do_cache_https_urls = self::DEFAULT_VALUE_DO_CACHE_HTTPS_URLS;

    private $do_append_debug_message = self::DEFAULT_VALUE_DO_APPEND_DEBUG_MESSAGE;

    private $do_clear_on_save = self::DEFAULT_VALUE_DO_CLEAR_ON_SAVE;

    private $cache_exclusions = array();

    private $_settings_loaded_from = 'class';

    private static $instance;

/*Constants*/
    const CACHE_MODE_OFF                            = 'off';
    const CACHE_MODE_PHP                            = 'php';
    const CACHE_MODE_ENHANCED                       = 'enhanced';

    const DEFAULT_VALUE_CACHE_MODE                  = 'off';
    const DEFAULT_VALUE_DO_CACHE_HTTPS_URLS         = false;
    const DEFAULT_VALUE_DO_APPEND_DEBUG_MESSAGE     = false;
    const DEFAULT_VALUE_DO_CLEAR_ON_SAVE            = false;

    const OPTION_KEY_NAME_CACHE_MODE                = 'vwc_cache_mode';
    const OPTION_KEY_NAME_DO_CACHE_HTTPS_URLS       = 'vwc_do_cache_https_urls';
    const OPTION_KEY_NAME_DO_APPEND_DEBUG_MESSAGE   = 'vwc_do_append_debug_message';
    const OPTION_KEY_NAME_DO_CLEAR_ON_SAVE          = 'vwc_do_clear_on_save';

/*Property Access*/
    public function get_cache_mode()
    {
        return $this->cache_mode;
    }

    public function set_cache_mode( $cache_mode )
    {
        if( self::is_valid_cache_mode( $cache_mode ) )
        {
            $this->cache_mode = $cache_mode;
            return;
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

    public function set_cache_exclusions( $cache_exclusions )
    {
        $this->cache_exclusions = $cache_exclusions;
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

/*Database loading*/
    public function load_from_database()
    {
        $tmp = get_option( self::OPTION_KEY_NAME_CACHE_MODE, self::DEFAULT_VALUE_CACHE_MODE );
        if( ! self::is_valid_cache_mode( $tmp ) )
        {
            //TODO: Logging here
            $tmp = self::DEFAULT_VALUE_CACHE_MODE;
        }

        $this->set_cache_mode( $tmp );
        $this->set_do_cache_https_urls(     true == get_option( self::OPTION_KEY_NAME_DO_CACHE_HTTPS_URLS,     self::DEFAULT_VALUE_DO_CACHE_HTTPS_URLS ) );
        $this->set_do_append_debug_message( true == get_option( self::OPTION_KEY_NAME_DO_APPEND_DEBUG_MESSAGE, self::DEFAULT_VALUE_DO_APPEND_DEBUG_MESSAGE ) );
        $this->set_do_clear_on_save(        true == get_option( self::OPTION_KEY_NAME_DO_CLEAR_ON_SAVE,        self::DEFAULT_VALUE_DO_CLEAR_ON_SAVE ) );

        $this->_settings_loaded_from = 'database';
    }

    public function persist_to_database()
    {
        $auto_load = true;
        if( self::CACHE_MODE_OFF === $this->get_cache_mode() )
        {
            //Only autoload our settings if the cache is enabled
            $auto_load = false;
        }
        update_option( self::OPTION_KEY_NAME_CACHE_MODE,                $this->get_cache_mode(),                $auto_load );
        update_option( self::OPTION_KEY_NAME_DO_CACHE_HTTPS_URLS,       $this->get_do_cache_https_urls(),       $auto_load );
        update_option( self::OPTION_KEY_NAME_DO_APPEND_DEBUG_MESSAGE,   $this->get_do_append_debug_message(),   $auto_load );
        update_option( self::OPTION_KEY_NAME_DO_CLEAR_ON_SAVE,          $this->get_do_clear_on_save(),          $auto_load );
    }

/*Static Factory Methods*/
    public static function get_instance( $refresh_from_database = false )
    {
        if( ! self::$instance )
        {
            self::$instance = new self();
            if( $refresh_from_database )
            {
                self::$instance->load_from_database();
            }
        }

        return self::$instance;

    }

    private static function is_valid_cache_mode( $cache_mode )
    {
        switch( $cache_mode )
        {
            case self::CACHE_MODE_OFF:
            case self::CACHE_MODE_PHP:
            case self::CACHE_MODE_ENHANCED:
                return true;
        }

        return false;
    }
}
