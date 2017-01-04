<?php

namespace Vendi\Cache\AjaxCallbacks;

use Vendi\Cache\ajax_message;
use Vendi\Cache\utils;
use Vendi\Cache\Legacy\wfCache;

class clear_page_cache extends ajax_callback_base
{
    public function get_result()
    {
        $cache_dir_name_safe = self::get_vwc_cache_settings()->get_cache_folder_name_safe();
        $stats = wfCache::clear_page_cache();
        if( $stats[ 'error' ] )
        {
            return new ajax_message(
                        __( 'Error occurred while clearing cache', 'Vendi Cache' ),
                        sprintf(
                                    __( 'A total of %1$d errors occurred while trying to clear your cache. The last error was: %2$s', 'Vendi Cache' ),
                                    $stats[ 'totalErrors' ],
                                    $stats[ 'error' ]
                                )
                    );
        }
        $body = sprintf(
                            __( 'A total of %1$d files were deleted and %2$d directories were removed. We cleared a total of %3$s of data in the cache.', 'Vendi Cache' ),
                            $stats[ 'filesDeleted' ],
                            $stats[ 'dirsDeleted' ],
                            esc_html( size_format( $stats[ 'totalData' ] * KB_IN_BYTES ) )
                        );

        if( $stats[ 'totalErrors' ] > 0 )
        {
            $body .= sprintf(
                                __( ' A total of %1$s errors were encountered. This probably means that we could not remove some of the files or directories in the cache. Please use your CPanel or file manager to remove the rest of the files in the directory: %2$s', 'Vendi Cache' ),
                                $stats[ 'totalErrors' ],
                                WP_CONTENT_DIR . '/' . $cache_dir_name_safe . '/'
                            );

        }

        return new ajax_message( __( 'Page Cache Cleared', 'Vendi Cache' ), $body );
    }
}
