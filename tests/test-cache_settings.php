<?php

use Vendi\WordPress\Caching;

class test_cache_settings extends WP_UnitTestCase
{
    public function test_nothing()
    {
        $this->assertSame( true, true );
    }
}
