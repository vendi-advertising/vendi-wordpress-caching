<?php

namespace Vendi\Cache;

use Vendi\Cache\Legacy\wfCache;
use Vendi\Cache\Legacy\wfUtils;
use Vendi\Cache\cache_settings;

class api
{

    const FILTER_NAME_DO_NOT_CACHE = 'vendi-cache/do-not-cache';
    /**
     * This is a legacy function used to invoke a newly implemented filter.
     * Please use the filter 'vendi-cache/do-not-cache' instead.
     *
     * @since  1.2.0
     */
    public static function do_not_cache()
    {
        add_filter( \Vendi\Cache\api::FILTER_NAME_DO_NOT_CACHE, '__return_true' );

        return true;
    }
}
