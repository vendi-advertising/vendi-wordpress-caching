<?php

use Vendi\Cache\cache_settings;

class test_cache_settings extends WP_UnitTestCase
{

    private $_instances = array();

    public function tearDown()
    {

        $ref = new \ReflectionClass( 'Vendi\Cache\cache_settings' );

        $cons = $ref->getConstants();

        //Erase each instance
        foreach( $this->_instances as $i => $obj )
        {
            //Unset any existing constants
            foreach( $cons as $name => $value )
            {
                if( 0 === strpos( $name, 'OPTION_KEY_NAME_' ) )
                {
                    $obj->delete_option_for_instance( constant( "\Vendi\Cache\cache_settings::$name" ) );
                }
            }

            cache_settings::erase_instance( $i );
        }

        //Reset the master array since this is called on each test
        $this->_instances = array();
    }

    /**
     * @covers Vendi\Cache\cache_settings::uninstall
     */
    public function test_uninstall()
    {
        $options = array(
                            cache_settings::OPTION_KEY_NAME_CACHE_MODE,
                            cache_settings::OPTION_KEY_NAME_DO_CACHE_HTTPS_URLS,
                            cache_settings::OPTION_KEY_NAME_DO_APPEND_DEBUG_MESSAGE,
                            cache_settings::OPTION_KEY_NAME_DO_CLEAR_ON_SAVE,
                            cache_settings::OPTION_KEY_NAME_CACHE_EXCLUSIONS,
            );

        //Grab our settings instance
        $settings = $this->_get_new_setting_instance();

        //None of the cache keys should exist
        foreach( $options as $option )
        {
            $this->assertFalse( $settings->get_option_for_instance( $option ) );
        }

        //Set each one
        foreach( $options as $option )
        {
            $settings->update_option_for_instance( $option, 'CHEESE' );
        }

        //Make sure that they have a value
        foreach( $options as $option )
        {
            $this->assertSame( 'CHEESE', $settings->get_option_for_instance( $option ) );
        }

        //Run the instance-specific uninstaller
        $settings->uninstall();

        //The keys should no longer exist again
        foreach( $options as $option )
        {
            $this->assertFalse( $settings->get_option_for_instance( $option ) );
        }
    }

    /**
     * @covers Vendi\Cache\cache_settings::has_instance
     */
    public function test_has_instance()
    {
        $test_instance_id = $this->_get_new_setting_instance_id_but_do_not_create();

        $this->assertFalse( cache_settings::has_instance( $test_instance_id ) );

        $settings = $this->_get_new_setting_instance( $test_instance_id );
        $this->assertTrue( cache_settings::has_instance( $test_instance_id ) );
    }

    /**
     * @covers Vendi\Cache\cache_settings::erase_instance
     */
    public function test_erase_instance()
    {
        $test_instance_id = $this->_get_new_setting_instance_id_but_do_not_create();

        $this->assertFalse( cache_settings::has_instance( $test_instance_id ) );

        $settings = $this->_get_new_setting_instance( $test_instance_id );
        $this->assertTrue( cache_settings::has_instance( $test_instance_id ) );

        cache_settings::erase_instance( $test_instance_id );

        $this->assertFalse( cache_settings::has_instance( $test_instance_id ) );
    }

    /**
     * @covers Vendi\Cache\cache_settings::get_instance
     */
    public function test_get_instance()
    {
        $settings = $this->_get_new_setting_instance();

        $this->assertTrue( $settings instanceof cache_settings );
        $this->assertSame( $settings, cache_settings::get_instance( $settings->get_instance_id() ) );
        $this->assertNotSame( $settings, $this->_get_new_setting_instance() );
    }

    /**
     * @covers Vendi\Cache\cache_settings::get_cache_exclusions
     * @covers Vendi\Cache\cache_settings::add_single_cache_exclusion
     */
    public function test_add_get_cache_exclusions()
    {
        $settings = $this->_get_new_setting_instance();
        $this->assertEmpty( $settings->get_cache_exclusions() );

        $pt = 'ALPHA';
        $p = 'BETA';
        $id = -1;

        $settings->add_single_cache_exclusion( $pt, $p, $id );

        $result = $settings->get_cache_exclusions();
        $this->assertCount( 1, $result );

        $t = reset( $result );

        $this->assertArrayHasKey( 'pt', $t ) ;
        $this->assertArrayHasKey( 'p', $t ) ;
        $this->assertArrayHasKey( 'id', $t ) ;

        $this->assertSame( $pt, $t[ 'pt' ] );
        $this->assertSame( $p,  $t[ 'p' ] );
        $this->assertSame( $id, $t[ 'id' ] );
    }

    /**
     * @covers Vendi\Cache\cache_settings::is_any_cache_mode_enabled
     * @covers Vendi\Cache\cache_settings::set_cache_mode
     */
    public function test_is_any_cache_mode_enabled()
    {
        $settings = $this->_get_new_setting_instance();
        $this->assertFalse( $settings->is_any_cache_mode_enabled() );
        $settings->set_cache_mode( cache_settings::CACHE_MODE_OFF );
        $this->assertFalse( $settings->is_any_cache_mode_enabled() );
        $settings->set_cache_mode( cache_settings::CACHE_MODE_PHP );
        $this->assertTrue( $settings->is_any_cache_mode_enabled() );
        $settings->set_cache_mode( cache_settings::CACHE_MODE_ENHANCED );
        $this->assertTrue( $settings->is_any_cache_mode_enabled() );
    }

    /**
     * @covers Vendi\Cache\cache_settings::set_cache_mode
     * @expectedException Vendi\Cache\cache_setting_exception
     */
    public function test_is_any_cache_mode_enabled_invalid()
    {
        $settings = $this->_get_new_setting_instance();
        $settings->set_cache_mode( 'CHEESE' );
    }

    /**
     * @covers Vendi\Cache\cache_settings::add_single_cache_exclusion
     */
    public function test_add_single_cache_exclusions()
    {
        $settings = $this->_get_new_setting_instance();
        $this->assertEmpty( $settings->get_cache_exclusions() );

        $settings->add_single_cache_exclusion( 'ALPHA', 'BETA' );

        $result = $settings->get_cache_exclusions();
        $this->assertCount( 1, $result );
        $t = reset( $result );

        $this->assertArrayHasKey( 'id', $t ) ;
        $this->assertTrue( is_float( $t[ 'id' ] ) );
    }

    private function _get_new_setting_instance_id_but_do_not_create()
    {
        $test_instance_id = null;

        while( true )
        {
            $test_instance_id = uniqid();
            if( ! cache_settings::has_instance( $test_instance_id ) )
            {
                return $test_instance_id;
            }
        }
    }

    private function _get_new_setting_instance( $test_instance_id = null )
    {
        if( ! $test_instance_id )
        {
            $test_instance_id = $this->_get_new_setting_instance_id_but_do_not_create();
        }

        $obj = cache_settings::get_instance( $test_instance_id );
        $this->_instances[ $test_instance_id ] = $obj;
        return $obj;
    }
}
