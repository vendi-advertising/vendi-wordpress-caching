<?php

use Vendi\Cache;
use Vendi\Cache\cache_settings;
use Vendi\Cache\Legacy\wfCache;

class test_wfCache extends WP_UnitTestCase
{
    /**
     * @covers Vendi\Cache\Legacy\wfCache::get_vwc_cache_settings
     */
    public function test_get_vwc_cache_settings()
    {
        $this->assertInstanceOf( cache_settings::class, wfCache::get_vwc_cache_settings() );
    }

    /**
     * @covers Vendi\Cache\Legacy\wfCache::is_cachable_test_exclusions
     */
    public function test_is_cachable_test_exclusions()
    {
        //Test empty parameters
        \Vendi\Cache\Legacy\wfCache::is_cachable_test_exclusions();
    }

    /**
     * @dataProvider provider_get_hosts_for_cache_filename
     * @covers Vendi\Cache\Legacy\wfCache::sanitize_host_for_cache_filename
     */
    public function test_sanitize_host_for_cache_filename( $expected, $actual )
    {
        $this->assertSame( \Vendi\Cache\Legacy\wfCache::sanitize_host_for_cache_filename( $expected ), $actual );
    }

    /**
     * @covers Vendi\Cache\Legacy\wfCache::cache_directory_test
     */
    public function test_cache_directory_test()
    {
        \Vendi\Cache\Legacy\wfCache::cache_directory_test();

        $file = WP_CONTENT_DIR . '/gerp.del_me';
        touch( $file );
        $this->assertContains( "The directory $file does not exist and we could not create it.", \Vendi\Cache\Legacy\wfCache::cache_directory_test( $file ) );
        unlink( $file );
    }

    /**
     * @covers Vendi\Cache\Legacy\wfCache::file_from_uri
     * @todo This test exists to call code in a PHP5.3 environment but doesn't really test anything.
     */
    public function test_file_from_uri()
    {

        $host = 'example.net';
        $uri = '/example/test/something/';

        $file = \Vendi\Cache\Legacy\wfCache::file_from_uri( $host, $uri, true );

        $expected = WP_CONTENT_DIR . '/vendi_cache/';
    }

    public function provider_get_hosts_for_cache_filename()
    {
        return array(
                        array( 'example.net', 'example.net' ),
                        array( 'test.example.net', 'test.example.net' ),
                        array( '41.example.net', '41.example.net' ),
                        array( 'test-test.example.net', 'test-test.example.net' ),
                        array( 'example.NET', 'example.NET' ),
                        array( 'e$$xample.net', 'example.net' ),
                );
    }
}
