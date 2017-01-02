<?php

use Vendi\Cache;

class test_cache_stats extends WP_UnitTestCase
{
    /**
     * @dataProvider provider_get_all_increments
     * @covers Vendi\Cache\cache_stats::increment_dir_count
     * @covers Vendi\Cache\cache_stats::increment_file_count
     * @covers Vendi\Cache\cache_stats::increment_compressed_file_count
     * @covers Vendi\Cache\cache_stats::increment_uncompressed_file_count
     */
    public function test_all_increments( $property, $method )
    {
        $cs = new \Vendi\Cache\cache_stats();

        $this->assertSame( 0, $cs->$property );
        $cs->$method();
        $this->assertSame( 1, $cs->$property );

    }

    /**
     * @dataProvider provider_get_all_adders
     * @covers Vendi\Cache\cache_stats::add_size_to_data
     * @covers Vendi\Cache\cache_stats::add_bytes_to_compressed_file_size
     * @covers Vendi\Cache\cache_stats::add_bytes_to_uncompressed_file_size
     */
    public function test_all_adders( $property, $method )
    {
        $cs = new \Vendi\Cache\cache_stats();

        $this->assertSame( 0, $cs->$property );
        $cs->$method( 100 );
        $this->assertSame( 100, $cs->$property );
        $cs->$method( 200 );
        $this->assertSame( 300, $cs->$property );

    }

    /**
     * @covers Vendi\Cache\cache_stats::maybe_set_oldest_newest_file
     * @covers Vendi\Cache\cache_stats::maybe_set_newest_file
     * @covers Vendi\Cache\cache_stats::maybe_set_oldest_file
     */
    public function test_maybe_set_oldest_newest_file()
    {
        $cs = new \Vendi\Cache\cache_stats();

        //Check defaults
        $this->assertSame( PHP_INT_MAX, $cs->oldestFile );
        $this->assertSame( 0, $cs->newestFile );

        //First call, both oldest and newest should be the same
        $cs->maybe_set_oldest_newest_file( 400 );
        $this->assertSame( 400, $cs->oldestFile );
        $this->assertSame( 400, $cs->newestFile );

        //Set a newer file, oldest should stay the same
        $cs->maybe_set_oldest_newest_file( 500 );
        $this->assertSame( 400, $cs->oldestFile );
        $this->assertSame( 500, $cs->newestFile );

        //Set an older file, newest should stay the same
        $cs->maybe_set_oldest_newest_file( 300 );
        $this->assertSame( 300, $cs->oldestFile );
        $this->assertSame( 500, $cs->newestFile );
    }

    /**
     * @covers Vendi\Cache\cache_stats::maybe_set_largest_file_size
     */
    public function test_maybe_set_largest_file_size()
    {
        $cs = new \Vendi\Cache\cache_stats();

        //Check the default
        $this->assertSame( 0, $cs->largestFile );

        //Set a higher value, expect change
        $cs->maybe_set_largest_file_size( 100 );
        $this->assertSame( 100, $cs->largestFile );

        //Set a lower value, expect no change
        $cs->maybe_set_largest_file_size( 20 );
        $this->assertSame( 100, $cs->largestFile );
    }

    /**
     * @covers Vendi\Cache\cache_stats::get_message_array_for_ajax
     */
    public function test_get_message_array_for_ajax_empty_files()
    {
        $cs = new \Vendi\Cache\cache_stats();
        $ajax = $cs->get_message_array_for_ajax();
        $this->assertSame( 'The cache is currently empty. It may be disabled or it may have been recently cleared.', $ajax[ 'body' ] );
    }

    /**
     * @covers Vendi\Cache\cache_stats::get_body_lines_basic
     */
    public function test_get_body_lines_basic()
    {
        $cs = new \Vendi\Cache\cache_stats();

        for( $i = 0; $i < 1000; $i++ )
        {
            $cs->increment_file_count();
            $cs->increment_dir_count();
        }
        $cs->add_size_to_data( 1024 * 7 );
        $data = $cs->get_body_lines_basic();
        $this->assertContains( 'Total files in cache: 1,000', $data );
        $this->assertContains( 'Total directories in cache: 1,000', $data );
        $this->assertContains( 'Total data: 7 KB', $data );
    }

    /**
     * @covers Vendi\Cache\cache_stats::get_body_lines_compressed_files
     */
    public function test_get_body_lines_compressed_files()
    {
        $cs = new \Vendi\Cache\cache_stats();

        for( $i = 0; $i < 1000; $i++ )
        {
            $cs->increment_uncompressed_file_count();
            $cs->increment_compressed_file_count();
        }
        $cs->add_bytes_to_uncompressed_file_size( 1024 * 8 );
        $cs->add_bytes_to_compressed_file_size( 1024 * 9 );

        $data = $cs->get_body_lines_compressed_files();

        $this->assertContains( 'Files: 1,000', $data );
        $this->assertContains( 'Data: 8 KB', $data );
        $this->assertContains( 'Compressed files: 1,000', $data );
        $this->assertContains( 'Compressed data: 9 KB', $data );
    }

    /**
     * @covers Vendi\Cache\cache_stats::get_body_lines_largest_file
     */
    public function test_get_body_lines_largest_file()
    {
        $cs = new \Vendi\Cache\cache_stats();
        $cs->maybe_set_largest_file_size( 1024 * 10 );

        $data = $cs->get_body_lines_largest_file();

        $this->assertContains( 'Largest file: 10 KB', $data );
    }

    /**
     * @covers Vendi\Cache\cache_stats::get_body_lines_oldest_file
     */
    public function test_get_body_lines_oldest_file()
    {
        $cs = new \Vendi\Cache\cache_stats();

        $now = time();

        $cs->maybe_set_oldest_newest_file( $now - 200 );
        $data = $cs->get_body_lines_oldest_file( $now );
        $this->assertContains( 'Oldest file in cache created 200 seconds ago', $data );

        $cs->maybe_set_oldest_newest_file( $now - ( 60 * 60 * 7 ) );
        $data = $cs->get_body_lines_oldest_file( $now );
        $this->assertContains( 'Oldest file in cache created 7 hours ago', $data );
    }

    /**
     * @covers Vendi\Cache\cache_stats::get_body_lines_newest_file
     */
    public function test_get_body_lines_newest_file()
    {
        $cs = new \Vendi\Cache\cache_stats();

        $now = time();

        $cs->maybe_set_oldest_newest_file( $now );

        $cs->maybe_set_oldest_newest_file( $now + 155 );
        $data = $cs->get_body_lines_newest_file( $now );
        $this->assertContains( 'Newest file in cache created 155 seconds ago', $data );

        $cs->maybe_set_oldest_newest_file( $now + ( 60 * 60 * 8 ) );
        $data = $cs->get_body_lines_newest_file( $now );
        $this->assertContains( 'Newest file in cache created 8 hours ago', $data );
    }

    /**
     * @covers Vendi\Cache\cache_stats::get_message_array_for_ajax
     */
    public function test_get_message_array_for_ajax()
    {
        $cs = new \Vendi\Cache\cache_stats();
        $cs->increment_uncompressed_file_count();
        $cs->increment_compressed_file_count();
        $cs->increment_file_count();
        $cs->increment_dir_count();
        $cs->maybe_set_oldest_newest_file( 100 );

        $ajax_data = $cs->get_message_array_for_ajax();

        $this->assertArrayHasKey( 'ok', $ajax_data );
        $this->assertArrayHasKey( 'heading', $ajax_data );
        $this->assertArrayHasKey( 'body', $ajax_data );
    }

    /**
     * @covers Vendi\Cache\cache_stats::get_body_lines_oldest_file
     * @covers Vendi\Cache\cache_stats::get_body_lines_newest_file
     */
    public function test_body_lines_old_new_default_parameter()
    {
        $cs = new \Vendi\Cache\cache_stats();
        $cs->maybe_set_oldest_newest_file( 100 );
        $this->assertCount( 1, $cs->get_body_lines_oldest_file() );
        $this->assertCount( 1, $cs->get_body_lines_newest_file() );
    }

    public function provider_get_all_increments()
    {
        return array(
                        array( 'dirs', 'increment_dir_count' ),
                        array( 'files', 'increment_file_count' ),
                        array( 'compressedFiles', 'increment_compressed_file_count' ),
                        array( 'uncompressedFiles', 'increment_uncompressed_file_count' ),
                );
    }

    public function provider_get_all_adders()
    {
        return array(
                        array( 'data', 'add_size_to_data' ),
                        array( 'compressedBytes', 'add_bytes_to_compressed_file_size' ),
                        array( 'uncompressedBytes', 'add_bytes_to_uncompressed_file_size' ),
                );
    }
}
