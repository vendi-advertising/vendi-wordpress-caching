<?php

namespace Vendi\Cache\AjaxCallbacks;

use Vendi\Cache\cache_setting_exclusion;
use Vendi\Cache\cache_settings;
use Vendi\Cache\ajax_message;
use Vendi\Cache\ajax_error;
use Vendi\Cache\Legacy\wfCache;
use Vendi\Shared\utils;

class add_cache_exclusion extends ajax_callback_base
{
    public function get_result()
    {
        $settings = self::get_vwc_cache_settings();
        $settings->add_single_cache_exclusion( utils::get_post_value( 'patternType' ), utils::get_post_value( 'pattern' ) );
        wfCache::schedule_cache_clear();
        if( $settings->get_cache_mode() == cache_settings::CACHE_MODE_ENHANCED )
        {
            if( in_array( utils::get_post_value( 'patternType' ), array( cache_setting_exclusion::USER_AGENT_CONTAINS, cache_setting_exclusion::USER_AGENT_MATCHES_EXACTLY, cache_setting_exclusion::COOKIE_NAME_CONTAINS ) ) )
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
