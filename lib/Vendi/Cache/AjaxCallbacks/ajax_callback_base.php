<?php

namespace Vendi\Cache\AjaxCallbacks;

use Vendi\Cache\cache_settings;

abstract class ajax_callback_base
{
    protected static $vwc_cache_settings;

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

    abstract public function get_result();
}
