<?php

namespace Vendi\Cache;

use Apix\Log\Logger;

/**
 * Utility class generally for HTTP.
 */
class logging
{
    private static $_logger;

    public static function get_logger()
    {
        if( ! self::$_logger )
        {
            self::$_logger = new Logger\File( VENDI_CACHE_PATH . 'debug.log');
            self::$_logger->setMinLevel('debug');
            self::$_logger->setDeferred(true);
        }

        return self::$_logger;
    }
}
