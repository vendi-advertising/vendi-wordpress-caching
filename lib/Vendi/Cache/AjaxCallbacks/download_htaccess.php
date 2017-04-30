<?php

namespace Vendi\Cache\AjaxCallbacks;

use Vendi\Cache\cache_settings;
use Vendi\Cache\ajax_message;
use Vendi\Cache\ajax_error;
use Vendi\Cache\Legacy\wfCache;

class download_htaccess extends ajax_callback_base
{
    public function get_result()
    {
        $url = site_url();
        $url = preg_replace( '/^https?:\/\//i', '', $url );
        $url = preg_replace( '/[^a-zA-Z0-9\.]+/', '_', $url );
        $url = preg_replace( '/^_+/', '', $url );
        $url = preg_replace( '/_+$/', '', $url );
        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: attachment; filename="htaccess_Backup_for_' . $url . '.txt"' );
        $file = wfCache::get_htaccess_path();
        readfile( $file );
        die();
    }
}
