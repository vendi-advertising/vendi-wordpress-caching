<?php

namespace Vendi\Cache\AjaxCallbacks;

use Vendi\Cache\cache_settings;
use Vendi\Cache\ajax_message;
use Vendi\Cache\ajax_error;
use Vendi\Cache\Legacy\wfCache;
use Vendi\Shared\utils;

class remove_cache_exclusion extends ajax_callback_base
{
    public function get_result()
    {
        $id = utils::get_post_value( 'id' );
        $ex = self::get_vwc_cache_settings()->get_cache_exclusions();
        if( ! $ex || 0 === count( $ex ) )
        {
            return array( 'ok' => 1 );
        }
        $rewriteHtaccess = false;
        for( $i = 0; $i < sizeof( $ex ); $i++ )
        {
            if( (string)$ex[ $i ][ 'id' ] == (string)$id )
            {
                if( self::get_vwc_cache_settings()->get_cache_mode() == cache_settings::CACHE_MODE_ENHANCED && preg_match( '/^(?:uac|uaeq|cc)$/', $ex[ $i ][ 'pt' ] ) )
                {
                    $rewriteHtaccess = true;
                }
                array_splice( $ex, $i, 1 );
                //Dont break in case of dups
            }
        }
        self::get_vwc_cache_settings()->set_cache_exclusions( $ex );
        if( $rewriteHtaccess && wfCache::add_htaccess_code( 'add' ) )
        {
            //rewrites htaccess rules
            return new ajax_error( 'We removed that rule but could not rewrite your .htaccess file. You\'re going to have to manually remove this rule from your .htaccess file. Please reload this page now.' );
        }
        return array( 'ok' => 1 );
    }
}
