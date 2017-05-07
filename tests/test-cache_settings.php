<?php

use Vendi\Cache\cache_settings;

class test_cache_settings extends WP_UnitTestCase
{

    private $_instances = [];

    public function tearDown()
    {
        //Erase each instance
        foreach( $this->_instances as $i => $obj )
        {
            cache_settings::erase_instance( $i );
        }

        //Reset the master array since this is called on each test
        $this->_instances = [];

        //Unset any existing constants
        foreach( ( new \ReflectionClass( 'Vendi\Cache\cache_settings' ) )->getConstants() as $name => $value )
        {
            if( 0 === strpos( $name, 'OPTION_KEY_NAME_' ) )
            {
                delete_option( constant( "\Vendi\Cache\cache_settings::$name" ) );
            }
        }
    }


    /**
     * @covers Vendi\Cache\cache_settings::get_instance
     */
    public function test_get_cache_mode()
    {
        $settings = $this->_get_new_setting_instance();

        $this->assertTrue( $settings instanceof cache_settings );
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

    private function _get_new_setting_instance()
    {
        $test_instance_id = null;

        while( true )
        {
            $test_instance_id = uniqid();
            if( ! cache_settings::has_instance( $test_instance_id ) )
            {
                break;
            }
        }

        $obj = cache_settings::get_instance( $test_instance_id );
        $this->_instances[ $test_instance_id ] = $obj;
        return $obj;
    }
}
