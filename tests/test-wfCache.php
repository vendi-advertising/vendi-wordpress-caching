<?php

use Vendi\Cache;

class test_wfCache extends WP_UnitTestCase
{
    /**
     * @dataProvider provider_get_hosts_for_cache_filename
     * @covers Vendi\Cache\Legacy\wfCache::sanitize_host_for_cache_filename
     */
    public function test_sanitize_host_for_cache_filename( $expected, $actual )
    {
        $this->assertSame( \Vendi\Cache\Legacy\wfCache::sanitize_host_for_cache_filename( $expected ), $actual );
    }

    // /**
    //  * @covers Vendi\Cache\Legacy\wfCache::file_from_uri
    //  */
    // public function test_file_from_uri()
    // {

    //     $host = 'example.net';
    //     $uri = '/example/test/something/';

    //     $file = \Vendi\Cache\Legacy\wfCache::file_from_uri( $host, $uri, true );

    //     $expected = WP_CONTENT_DIR . '/vendi_cache/';

    //     dump( $expected );
    //     dump( $file );
    // }

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
