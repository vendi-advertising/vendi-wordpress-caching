<?php

namespace Vendi\Cache\Exceptions;


class file_system_exception extends \Exception
{
    private $_error_get_last;

    public function __construct( $message = null, array $error_get_last = null )
    {
        parent::__construct( $message );
        $this->error_get_last = $error_get_last;
    }

    public function __toString()
    {
        if( $this->error_get_last )
        {
            $type    = $this->error_get_last[ 'type' ];
            $message = $this->error_get_last[ 'message' ];
            $file    = $this->error_get_last[ 'file' ];
            $line    = $this->error_get_last[ 'line' ];

            return __CLASS__ . ": [{$this->code}]: [Type: {$type}] [File: {$file}] [Line: {$line}] [Primary: {$this->message}] [Secondary: {$message}]\n";
        }

        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
