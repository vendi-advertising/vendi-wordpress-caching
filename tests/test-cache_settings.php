<?php

use Vendi\Cache\cache_settings;

class test_cache_settings extends WP_UnitTestCase
{
    public function test_get_cache_mode()
    {
        $obj = cache_settings::get_instance();

        $this->assertTrue( $obj instanceof cache_settings );
    }
}
