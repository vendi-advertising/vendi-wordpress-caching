<?php

namespace Vendi\Cache\AjaxCallbacks;

use Vendi\Cache\Legacy\wfCache;
use Vendi\Shared\utils;

class remove_from_cache extends ajax_callback_base
{
    public function get_result()
    {
        $id = utils::get_post_value( 'id' );
        $link = get_permalink( $id );
        if( preg_match( '/^https?:\/\/([^\/]+)(.*)$/i', $link, $matches ) )
        {
            $host = $matches[ 1 ];
            $URI = $matches[ 2 ];
            if( ! $URI )
            {
                $URI = '/';
            }
            $sslFile = wfCache::file_from_uri( $host, $URI, true ); //SSL
            $normalFile = wfCache::file_from_uri( $host, $URI, false ); //non-SSL
            @unlink( $sslFile );
            @unlink( $sslFile . '_gzip' );
            @unlink( $normalFile );
            @unlink( $normalFile . '_gzip' );
        }
        return array( 'ok' => 1 );
    }
}
