<?php

use Vendi\Cache\AjaxCallbacks\check_falcon_htaccess;

class test_check_falcon_htaccess extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Vendi\Cache\AjaxCallbacks\check_falcon_htaccess::get_result
     */
    public function test_get_result()
    {
        $obj = new check_falcon_htaccess();
        $obj->get_result();
    }
}
