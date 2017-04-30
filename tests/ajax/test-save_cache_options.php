<?php

use Vendi\Cache\AjaxCallbacks\save_cache_options;

class test_save_cache_options extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Vendi\Cache\AjaxCallbacks\save_cache_options::get_result
     */
    public function test_get_result()
    {
        ( new save_cache_options() )->get_result();
    }
}
