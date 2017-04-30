<?php

use Vendi\Cache\AjaxCallbacks\save_cache_config;

class test_save_cache_config extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Vendi\Cache\AjaxCallbacks\save_cache_config::get_result
     */
    public function test_get_result()
    {
        ( new save_cache_config() )->get_result();
    }
}
