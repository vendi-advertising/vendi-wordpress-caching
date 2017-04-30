<?php

namespace Vendi\Cache\AjaxCallbacks;

use Vendi\Cache\cache_setting_exception;
use Vendi\Cache\cache_settings;
use Vendi\Cache\ajax_message;
use Vendi\Cache\ajax_error;
use Vendi\Cache\utils;
use Vendi\Cache\Legacy\wfCache;

class add_cache_exclusion extends ajax_callback_base
{
    public function get_result()
    {
        $ex = self::get_vwc_cache_settings()->get_cache_exclusions();
        $ex[ ] = array(
            'pt' => utils::get_post_value( 'patternType' ),
            'p' => utils::get_post_value( 'pattern' ),
            'id' => microtime( true ),
            );

        self::get_vwc_cache_settings()->set_cache_exclusions( $ex );
        wfCache::schedule_cache_clear();
        if( self::get_vwc_cache_settings()->get_cache_mode() == cache_settings::CACHE_MODE_ENHANCED ) )
        {
            if( in_array( utils::get_post_value( 'patternType' ), array( cache_setting_exception::USER_AGENT_CONTAINS, cache_setting_exception::USER_AGENT_MATCHES_EXACTLY, cache_setting_exception::COOKIE_NAME_CONTAINS ) ) )
            {
                //rewrites htaccess rules
                if( wfCache::add_htaccess_code( 'add' ) )
                {
                    return new ajax_error( esc_html__( 'We added the rule you requested but could not modify your .htaccess file. Please delete this rule, check the permissions on your .htaccess file and then try again.', 'Vendi Cache' ) );
                }
            }
        }
        return new ajax_message();
    }
}
