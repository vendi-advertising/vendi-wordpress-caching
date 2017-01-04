<?php

namespace Vendi\Cache\AjaxCallbacks;

use Vendi\Cache\cache_settings;
use Vendi\Cache\ajax_message;
use Vendi\Cache\ajax_error;
use Vendi\Cache\utils;
use Vendi\Cache\Legacy\wfCache;

class save_cache_options extends ajax_callback_base
{
    public function get_result()
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
}
