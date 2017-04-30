<?php

use Vendi\Cache\AjaxCallbacks\remove_cache_exclusion;
use Vendi\Cache\cache_settings;

class test_remove_cache_exclusion extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        parent::tearDown();

        \Vendi\Shared\utils::reset_all_custom_arrays();
    }
    /**
     * @covers Vendi\Cache\AjaxCallbacks\remove_cache_exclusion::get_result
     */
    public function test_get_result()
    {
        // \Vendi\Shared\utils::$CUSTOM_POST = array(
        //                                             'id' => 100,
        //                                         );

        // $ex[ ] = array(
        //                 'pt' => utils::get_post_value( 'patternType' ),
        //                 'p' => utils::get_post_value( 'pattern' ),
        //                 'id' => microtime( true ),
        //             );

        $obj = new remove_cache_exclusion();
        $obj->get_result();

        // dump( cache_settings::get_instance()->get_cache_exclusions() );

        // dump( $obj->get_result() );
    }
}
