<?php

use Vendi\Cache\AjaxCallbacks\remove_from_cache;

class test_remove_from_cache extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Vendi\Cache\AjaxCallbacks\remove_from_cache::get_result
     */
    public function test_get_result()
    {
        ( new remove_from_cache() )->get_result();
    }
}
