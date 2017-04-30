<?php

namespace Vendi\Cache\AjaxCallbacks;

use Vendi\Cache\cache_settings;
use Vendi\Cache\ajax_message;
use Vendi\Cache\ajax_error;
use Vendi\Cache\Legacy\wfCache;
use Vendi\Shared\utils;

class load_cache_exclusions extends ajax_callback_base
{
    public function get_result()
    {
        $ex = self::get_vwc_cache_settings()->get_cache_exclusions();
        if( ! $ex || 0 === count( $ex ) )
        {
            return array( 'ex' => false );
        }
        return array( 'ex' => $ex );
    }
}
