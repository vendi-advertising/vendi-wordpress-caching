<?php

namespace Vendi\Cache;

use Vendi\Cache\Legacy\wfCache;
use Vendi\Cache\Legacy\wfUtils;
use Vendi\Cache\cache_settings;

/**
 *
 * @since 1.2.2
 */
class ajax_error implements \JsonSerializable
{
    private $_errorMsg;

    public function __construct( $errorMsg )
    {
        $this->_errorMsg = $errorMsg;
    }

    public function jsonSerialize()
    {
        $ret =  array(
                        'errorMsg' => $this->_errorMsg,
                    );

        // if( $this->_additional_data && is_array( $this->_additional_data ) && count( $this->_additional_data ) > 0 )
        // {
        //     $ret = array_merge( $ret, $this->_additional_data );
        // }

        return $ret;
    }
}
