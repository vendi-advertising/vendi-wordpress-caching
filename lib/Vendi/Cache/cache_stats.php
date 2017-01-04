<?php

namespace Vendi\Cache;

use Vendi\Cache\Legacy\wfCache;
use Vendi\Cache\Legacy\wfUtils;
use Vendi\Cache\cache_settings;

/**
 *
 * @since 1.2.0
 */
class cache_stats
{
    public $files = 0;

    public $dirs = 0;

    public $data = 0;

    public $compressedFiles = 0;

    public $compressedBytes = 0;

    public $uncompressedFiles = 0;

    public $uncompressedBytes = 0;

    public $oldestFile = PHP_INT_MAX;

    public $newestFile = 0;

    public $largestFile = 0;

    public function increment_dir_count()
    {
        $this->dirs++;
    }

    public function increment_file_count()
    {
        $this->files++;
    }

    public function add_size_to_data( $size )
    {
        $this->data += $size;
    }

    public function increment_compressed_file_count()
    {
        $this->compressedFiles++;
    }

    public function increment_uncompressed_file_count()
    {
        $this->uncompressedFiles++;
    }

    public function add_bytes_to_compressed_file_size( $size )
    {
        $this->compressedBytes += $size;
    }

    public function add_bytes_to_uncompressed_file_size( $size )
    {
        $this->uncompressedBytes += $size;
    }

    public function maybe_set_largest_file_size( $size )
    {
        $this->largestFile = max( $this->largestFile, $size );
    }

    public function maybe_set_oldest_file( $ctime )
    {
        $this->oldestFile = min( $this->oldestFile, $ctime );
    }

    public function maybe_set_newest_file( $ctime )
    {
        $this->newestFile = max( $this->newestFile, $ctime );
    }

    public function maybe_set_oldest_newest_file( $ctime )
    {
        $this->maybe_set_oldest_file( $ctime );
        $this->maybe_set_newest_file( $ctime );
    }

    public function get_body_lines_basic()
    {
        $body_lines = array();

        $body_lines[] = sprintf( __( 'Total files in cache: %s', 'Vendi Cache' ), number_format_i18n( $this->files ) );
        $body_lines[] = sprintf( __( 'Total directories in cache: %s', 'Vendi Cache' ), number_format_i18n( $this->dirs ) );

        //size_format calls into number_format_i18n which in turn calls apply_filters so it is technically
        //possible for something to return improper HTML and we should therefor escape it.
        $body_lines[] = sprintf( __( 'Total data: %s', 'Vendi Cache' ), esc_html( size_format( $this->data ) ) );

        return $body_lines;
    }

    public function get_body_lines_compressed_files()
    {
        $body_lines = array();

        if( $this->compressedFiles )
        {
            $body_lines[] = sprintf( __( 'Files: %s', 'Vendi Cache' ), number_format_i18n( $this->uncompressedFiles ) );
            $body_lines[] = sprintf( __( 'Data: %s', 'Vendi Cache' ), esc_html( size_format( $this->uncompressedBytes ) ) );
            $body_lines[] = sprintf( __( 'Compressed files: %s', 'Vendi Cache' ), number_format_i18n( $this->compressedFiles ) );
            $body_lines[] = sprintf( __( 'Compressed data: %s', 'Vendi Cache' ), esc_html( size_format( $this->compressedBytes ) ) );
        }

        return $body_lines;
    }

    public function get_body_lines_largest_file()
    {
        $body_lines = array();

        if( $this->largestFile )
        {
            $body_lines[] = sprintf( __( 'Largest file: %s', 'Vendi Cache' ), esc_html( size_format( $this->largestFile ) ) );
        }

        return $body_lines;
    }

    public function get_body_lines_oldest_file( $time_to_check_against = null )
    {
        $body_lines = array();

        if( $this->oldestFile )
        {
            if( null === $time_to_check_against )
            {
                $time_to_check_against = time();
            }

            if( $time_to_check_against - $this->oldestFile < 300 )
            {
                $body_lines[] = sprintf( __( 'Oldest file in cache created %s seconds ago', 'Vendi Cache' ), number_format_i18n( $time_to_check_against - $this->oldestFile ) );
            }
            else
            {
                $body_lines[] = sprintf( __( 'Oldest file in cache created %s ago', 'Vendi Cache' ), human_time_diff( $this->oldestFile ) );
            }
        }

        return $body_lines;
    }

    public function get_body_lines_newest_file( $time_to_check_against = null )
    {
        $body_lines = array();

        if( $this->newestFile )
        {
            if( null === $time_to_check_against )
            {
                $time_to_check_against = time();
            }

            if( $this->newestFile - $time_to_check_against < 300 )
            {
                $body_lines[] = sprintf( __( 'Newest file in cache created %s seconds ago', 'Vendi Cache' ), number_format_i18n( $this->newestFile - $time_to_check_against ) );
            }
            else
            {
                $body_lines[] = sprintf( __( 'Newest file in cache created %s ago', 'Vendi Cache' ), human_time_diff( $this->newestFile ) );
            }
        }

        return $body_lines;
    }

    /**
     * Get all of the cache stats for HTML/AJAX display.
     *
     * @return array
     */
    public function get_message_array_for_ajax()
    {
        if( ! $this->files )
        {
            return array(
                            'ok' => 1,
                            'heading' => __( 'Cache Stats', 'Vendi Cache' ),
                            'body' => __( 'The cache is currently empty. It may be disabled or it may have been recently cleared.', 'Vendi Cache' )
                        );
        }

        $body_lines = array();

        $body_lines += $this->get_body_lines_basic();

        $body_lines += $this->get_body_lines_compressed_files();

        $body_lines += $this->get_body_lines_largest_file();

        $body_lines += $this->get_body_lines_oldest_file();

        $body_lines += $this->get_body_lines_newest_file();

        return array(
                        'ok' => 1,
                        'heading' => __( 'Cache Stats', 'Vendi Cache' ),
                        'body' => implode( '<br />', $body_lines ),
                        'data' => $this
                );
    }
}
