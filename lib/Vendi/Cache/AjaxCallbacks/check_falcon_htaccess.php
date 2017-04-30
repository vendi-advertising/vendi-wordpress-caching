<?php

namespace Vendi\Cache\AjaxCallbacks;

use Vendi\Cache\cache_settings;
use Vendi\Cache\ajax_message;
use Vendi\Cache\ajax_error;
use Vendi\Cache\Legacy\wfCache;
use Vendi\Cache\Legacy\wfUtils;
use Vendi\Shared\utils;

class check_falcon_htaccess extends ajax_callback_base
{
    public function get_result()
    {
        if( wfUtils::isNginx() )
        {
            return array( 'nginx' => 1 );
        }
        $file = wfCache::get_htaccess_path();
        if( ! $file )
        {
            return array( 'err' => "We could not find your .htaccess file to modify it.", 'code' => wfCache::get_htaccess_code() );
        }
        $fh = @fopen( $file, 'r+' );
        if( ! $fh )
        {
            $err = error_get_last();
            return array( 'err' => "We found your .htaccess file but could not open it for writing: " . $err[ 'message' ], 'code' => wfCache::get_htaccess_code() );
        }
        return array( 'ok' => 1 );
    }
}
