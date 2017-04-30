<?php

namespace Vendi\Cache;

/**
 *
 * @since 1.2.2
 */
class ajax_message implements \JsonSerializable
{

    private $_ok;

    private $_heading;

    private $_body;

    private $_nonce;

    private $_additional_data;

    public function __construct( $heading = null, $body = null, $additional_data = null )
    {
        $this->_ok = 1;
        $this->_heading = $heading;
        $this->_body = $body;
        $this->_additional_data = $additional_data;
    }

    public function get_nonce( )
    {
        return $this->_nonce;
    }

    public function set_nonce( $nonce )
    {
        $this->_nonce = $nonce;
    }

    public static function create_from_legacy_array( $data )
    {
        if( ! is_array( $data ) )
        {
            $data = array( 'ok' => 1, 'heading' => 'Super Error', 'body' => 'Super Error', 'data' => $data );
        }

        $ok = null;
        $heading = null;
        $body = null;

        if( array_key_exists( 'ok', $data ) )
        {
            $ok = $data[ 'ok' ];
            unset( $data[ 'ok' ] );
        }

        if( array_key_exists( 'heading', $data ) )
        {
            $heading = $data[ 'heading' ];
            unset( $data[ 'heading' ] );
        }

        if( array_key_exists( 'body', $data ) )
        {
            $body = $data[ 'body' ];
            unset( $data[ 'body' ] );
        }

        return new self( $heading, $body, $data );
    }

    public static function create_success_message( $heading, $body )
    {
        return new self( $heading, $body );
    }

    public function jsonSerialize()
    {
        $ret =  array(
                        'ok'        => $this->_ok,
                        'heading'   => $this->_heading,
                        'body'      => $this->_body,
                    );

        if( $this->_additional_data && is_array( $this->_additional_data ) && count( $this->_additional_data ) > 0 )
        {
            $ret = array_merge( $ret, $this->_additional_data );
        }

        return $ret;
    }
}
