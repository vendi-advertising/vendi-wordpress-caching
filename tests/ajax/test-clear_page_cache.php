<?php

use Vendi\Cache\AjaxCallbacks\clear_page_cache;

class test_clear_page_cache extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Vendi\Cache\AjaxCallbacks\clear_page_cache::get_result
     */
    public function test_get_result()
    {
        $obj = new clear_page_cache();
        $obj->get_result();
    }
}
