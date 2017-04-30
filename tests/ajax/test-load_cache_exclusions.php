<?php

use Vendi\Cache\AjaxCallbacks\load_cache_exclusions;

class test_load_cache_exclusions extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Vendi\Cache\AjaxCallbacks\load_cache_exclusions::get_result
     */
    public function test_get_result()
    {
        ( new load_cache_exclusions() )->get_result();
    }
}
