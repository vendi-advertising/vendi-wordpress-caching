<?php

use Vendi\Cache\cache_setting_exception;

class test_cache_setting_exception extends WP_UnitTestCase
{
    /**
     * @dataProvider provider_test_constants
     */
    public function test_constants( $constant, $value )
    {
        $this->assertSame( constant( "Vendi\Cache\cache_setting_exception::$constant" ), $value );
    }

    public function provider_test_constants()
    {
        return array(
                        array( 'URL_STARTS_WITH',               's' ),
                        array( 'URL_ENDS_WITH',                 'e' ),
                        array( 'URL_CONTAINS',                  'c' ),
                        array( 'URL_MATCHES_EXACTLY',           'eq' ),
                        array( 'USER_AGENT_CONTAINS',           'uac' ),
                        array( 'USER_AGENT_MATCHES_EXACTLY',    'uaeq' ),
                        array( 'COOKIE_NAME_CONTAINS',          'cc' ),
                    );
    }
}
