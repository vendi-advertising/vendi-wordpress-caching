<?php

namespace Vendi\Cache;

class cache_settings
{
/*Fields*/
    private static $_instances = [];

    private $_instance_id = '';

    private $cache_folder_name_safe = null;

/*Constants*/
    const CACHE_MODE_OFF      = 'off';
    const CACHE_MODE_PHP      = 'php';
    const CACHE_MODE_ENHANCED = 'enhanced';

    const DEFAULT_VALUE_CACHE_MODE              = self::CACHE_MODE_OFF;
    const DEFAULT_VALUE_DO_CACHE_HTTPS_URLS     = false;
    const DEFAULT_VALUE_DO_APPEND_DEBUG_MESSAGE = false;
    const DEFAULT_VALUE_DO_CLEAR_ON_SAVE        = false;
    const DEFAULT_VALUE_CACHE_EXCLUSIONS        = null;

    const OPTION_KEY_NAME_CACHE_MODE              = 'vwc_cache_mode';
    const OPTION_KEY_NAME_DO_CACHE_HTTPS_URLS     = 'vwc_do_cache_https_urls';
    const OPTION_KEY_NAME_DO_APPEND_DEBUG_MESSAGE = 'vwc_do_append_debug_message';
    const OPTION_KEY_NAME_DO_CLEAR_ON_SAVE        = 'vwc_do_clear_on_save';
    const OPTION_KEY_NAME_CACHE_EXCLUSIONS        = 'vwc_cache_exclusions';

    const DEFAULT_INSTANCE_ID = 1;

    public function __construct( $instance_id )
    {
        $this->_instance_id = $instance_id;
    }

/*Property Access*/

    public function get_instance_id()
    {
        return $this->_instance_id;
    }

    public function get_cache_mode()
    {
        return get_option( self::OPTION_KEY_NAME_CACHE_MODE, self::DEFAULT_VALUE_CACHE_MODE );
    }

    public function set_cache_mode( $cache_mode )
    {
        if( self::is_valid_cache_mode( $cache_mode ) )
        {
            update_option( self::OPTION_KEY_NAME_CACHE_MODE, $cache_mode );
            return;
        }

        throw new cache_setting_exception( __( sprintf( 'Unknown cache mode: %1$s', $cache_mode ), 'Vendi Cache' ) );
    }

    public function get_do_cache_https_urls()
    {
        return true == get_option( self::OPTION_KEY_NAME_DO_CACHE_HTTPS_URLS, self::DEFAULT_VALUE_DO_CACHE_HTTPS_URLS );
    }

    /**
     * @param boolean $do_cache_https_urls
     */
    public function set_do_cache_https_urls( $do_cache_https_urls )
    {
        update_option( self::OPTION_KEY_NAME_DO_CACHE_HTTPS_URLS, $do_cache_https_urls );
    }

    public function get_do_append_debug_message()
    {
        return true == get_option( self::OPTION_KEY_NAME_DO_APPEND_DEBUG_MESSAGE, self::DEFAULT_VALUE_DO_APPEND_DEBUG_MESSAGE );
    }

    /**
     * @param boolean $do_append_debug_message
     */
    public function set_do_append_debug_message( $do_append_debug_message )
    {
        update_option( self::OPTION_KEY_NAME_DO_APPEND_DEBUG_MESSAGE, $do_append_debug_message );
    }

    public function get_do_clear_on_save()
    {
        return true == get_option( self::OPTION_KEY_NAME_DO_CLEAR_ON_SAVE, self::DEFAULT_VALUE_DO_CLEAR_ON_SAVE );
    }

    /**
     * @param boolean $do_clear_on_save
     */
    public function set_do_clear_on_save( $do_clear_on_save )
    {
        update_option( self::OPTION_KEY_NAME_DO_CLEAR_ON_SAVE, $do_clear_on_save );
    }

    public function get_cache_exclusions()
    {
        $tmp = get_option( self::OPTION_KEY_NAME_CACHE_EXCLUSIONS, self::DEFAULT_VALUE_CACHE_EXCLUSIONS );
        if( ! $tmp )
        {
            $tmp = array();
        }
        elseif( is_serialized( $tmp ) )
        {
            $tmp = unserialize( $tmp );
        }
        return $tmp;
    }

    public function set_cache_exclusions( $cache_exclusions )
    {
        if( ! is_serialized( $cache_exclusions ) )
        {
            $cache_exclusions = serialize( $cache_exclusions );
        }
        update_option( self::OPTION_KEY_NAME_CACHE_EXCLUSIONS, $cache_exclusions );
    }

    public function add_single_cache_exclusion( $patternType, $pattern, $id = null )
    {
        if( ! $id )
        {
            $id = microtime( true );
        }

        $ex = $this->get_cache_exclusions();
        $ex[] = array(
                        'pt' => $patternType,
                        'p'  => $pattern,
                        'id' => $id,
                    );

        $this->set_cache_exclusions( $ex );
    }

/*Methods*/
    /**
     * Check whether any cache mode is enabled.
     *
     * @return boolean True if the cache mode is php or enhanced, otherwise false.
     */
    public function is_any_cache_mode_enabled()
    {
        return $this->get_cache_mode() == cache_settings::CACHE_MODE_PHP || $this->get_cache_mode() == cache_settings::CACHE_MODE_ENHANCED;
    }

    public function get_cache_folder_name_safe()
    {
        if( ! $this->cache_folder_name_safe )
        {
            $this->cache_folder_name_safe = preg_replace( '/[^a-z_]+/', '', strtolower( VENDI_CACHE_FOLDER_NAME ) );

            if( ! $this->cache_folder_name_safe )
            {
                $this->cache_folder_name_safe = 'vendi_cache';
            }
        }

        return $this->cache_folder_name_safe;
    }

/*Database loading/saving/uninstall*/

    public static function uninstall()
    {
        delete_option( self::OPTION_KEY_NAME_CACHE_MODE );
        delete_option( self::OPTION_KEY_NAME_DO_CACHE_HTTPS_URLS );
        delete_option( self::OPTION_KEY_NAME_DO_APPEND_DEBUG_MESSAGE );
        delete_option( self::OPTION_KEY_NAME_DO_CLEAR_ON_SAVE );
        delete_option( self::OPTION_KEY_NAME_CACHE_EXCLUSIONS );
    }

/*Static Factory Methods*/
    public static function get_instance( $instance_id = self::DEFAULT_INSTANCE_ID )
    {
        if( ! self::has_instance( $instance_id ) )
        {
            self::$_instances[ $instance_id ] = new self( $instance_id );
        }
        return self::$_instances[ $instance_id ];

    }

    public static function has_instance( $instance_id )
    {
        return array_key_exists( $instance_id, self::$_instances );
    }

    public static function erase_instance( $instance_id )
    {
        if( self::has_instance( $instance_id ) )
        {
            unset( self::$_instances[ $instance_id ] );
        }
    }

/*Static Helpers*/
    public static function is_valid_cache_mode( $cache_mode )
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
