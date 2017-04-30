<?php

use Vendi\Cache\AjaxCallbacks\download_htaccess;

class test_download_htaccess extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Vendi\Cache\AjaxCallbacks\download_htaccess::get_result
     */
    public function test_get_result()
    {
        $obj = new download_htaccess();
        $obj->get_result();
    }
}
