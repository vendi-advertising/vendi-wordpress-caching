<?php

use Vendi\Cache\AjaxCallbacks\add_cache_exclusion;

class test_add_cache_exclusion extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Vendi\Cache\AjaxCallbacks\add_cache_exclusion::get_result
     */
    public function test_get_result()
    {
        $obj = new add_cache_exclusion();
        $obj->get_result();
    }
}
