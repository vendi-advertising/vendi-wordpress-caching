<?php

use Vendi\Cache;

class test_utils extends WP_UnitTestCase
{
    private $OLD_POST;
    private $OLD_GET;
    private $OLD_COOKIE;
    private $OLD_SERVER;

    public function setUp()
    {
        parent::setUp();

        $this->OLD_COOKIE = isset( $_COOKIE ) ? $_COOKIE : null;
        $this->OLD_SERVER = isset( $_SERVER ) ? $_SERVER : null;
        $this->OLD_GET    = isset( $_GET )    ? $_GET    : null;
        $this->OLD_POST   = isset( $_POST )   ? $_POST   : null;
    }

    public function tearDown()
    {
        $_COOKIE = $this->OLD_COOKIE;
        $_SERVER = $this->OLD_SERVER;
        $_GET    = $this->OLD_GET;
        $_POST   = $this->OLD_POST;
    }

    /**
     * @covers Vendi\Cache\utils::is_post
     */
    public function test_is_post()
    {
        $old = \Vendi\Cache\utils::get_server_value( 'REQUEST_METHOD', false );

        $_SERVER[ 'REQUEST_METHOD' ] = 'POST';

        $this->assertTrue( \Vendi\Cache\utils::is_post() );

        if( $old )
        {
            $_SERVER[ 'REQUEST_METHOD' ] = $old;
        }

    }

    /**
     * @covers Vendi\Cache\utils::is_integer_like
     */
    public function test_utils_is_integer_like()
    {
        $this->assertTrue( \Vendi\Cache\utils::is_integer_like( '1' ) );
        $this->assertTrue( \Vendi\Cache\utils::is_integer_like( '-1' ) );
        $this->assertTrue( \Vendi\Cache\utils::is_integer_like( 1 ) );
        $this->assertTrue( \Vendi\Cache\utils::is_integer_like( -1 ) );
        $this->assertTrue( \Vendi\Cache\utils::is_integer_like( 0 ) );
        $this->assertTrue( \Vendi\Cache\utils::is_integer_like( '999999999999999999999999999999999999999999999999999' ) );

        $this->assertFalse( \Vendi\Cache\utils::is_integer_like( 1.9 ) );
        $this->assertFalse( \Vendi\Cache\utils::is_integer_like( new \stdClass() ) );
        $this->assertFalse( \Vendi\Cache\utils::is_integer_like( array() ) );
        $this->assertFalse( \Vendi\Cache\utils::is_integer_like( false ) );
        $this->assertFalse( \Vendi\Cache\utils::is_integer_like( true ) );
        $this->assertFalse( \Vendi\Cache\utils::is_integer_like( '1.9' ) );
        $this->assertFalse( \Vendi\Cache\utils::is_integer_like( 'cheese' ) );
        $this->assertFalse( \Vendi\Cache\utils::is_integer_like( null ) );
    }

    /**
     * @covers Vendi\Cache\utils::get_post_value
     * @covers Vendi\Cache\utils::get_request_value
     */
    public function test_utils_get_post_value()
    {
        $_POST[ 'alpha' ] = 'beta';

        $this->assertSame( 'beta', \Vendi\Cache\utils::get_post_value( 'alpha' ) );
        $this->assertSame( 'beta', \Vendi\Cache\utils::get_post_value( 'alpha', 'BAD' ) );
        $this->assertSame( 'alpha', \Vendi\Cache\utils::get_post_value( 'BAD', 'alpha' ) );
    }

    /**
     * @covers Vendi\Cache\utils::get_get_value
     * @covers Vendi\Cache\utils::get_request_value
     */
    public function test_utils_get_get_value()
    {
        $_GET = array( 'alpha' => 'beta' );

        $this->assertSame( 'beta', \Vendi\Cache\utils::get_get_value( 'alpha' ) );
        $this->assertSame( 'beta', \Vendi\Cache\utils::get_get_value( 'alpha', 'BAD' ) );
        $this->assertSame( 'alpha', \Vendi\Cache\utils::get_get_value( 'BAD', 'alpha' ) );
    }

    /**
     * @covers Vendi\Cache\utils::get_server_value
     * @covers Vendi\Cache\utils::get_request_value
     */
    public function test_utils_get_server_value()
    {
        $_SERVER = array( 'alpha' => 'beta' );

        $this->assertSame( 'beta', \Vendi\Cache\utils::get_server_value( 'alpha' ) );
        $this->assertSame( 'beta', \Vendi\Cache\utils::get_server_value( 'alpha', 'BAD' ) );
        $this->assertSame( 'alpha', \Vendi\Cache\utils::get_server_value( 'BAD', 'alpha' ) );
    }

    /**
     * @covers Vendi\Cache\utils::get_cookie_value
     * @covers Vendi\Cache\utils::get_request_value
     */
    public function test_utils_get_cookie_value()
    {
        $_COOKIE = array( 'alpha' => 'beta' );

        $this->assertSame( 'beta', \Vendi\Cache\utils::get_cookie_value( 'alpha' ) );
        $this->assertSame( 'beta', \Vendi\Cache\utils::get_cookie_value( 'alpha', 'BAD' ) );
        $this->assertSame( 'alpha', \Vendi\Cache\utils::get_cookie_value( 'BAD', 'alpha' ) );
    }

    /**
     * @covers Vendi\Cache\utils::get_request_object
     */
    public function test_get_request_object()
    {
        $_POST = array( 'alpha' => 'beta' );
        $_GET = array( 'alpha' => 'beta' );
        $_SERVER = array( 'alpha' => 'beta' );
        $_COOKIE = array( 'alpha' => 'beta' );

        $this->assertSame( $_POST, \Vendi\Cache\utils::get_request_object( 'POST' ) );
        $this->assertSame( $_GET, \Vendi\Cache\utils::get_request_object( 'GET' ) );
        $this->assertSame( $_SERVER, \Vendi\Cache\utils::get_request_object( 'SERVER' ) );
        $this->assertSame( $_COOKIE, \Vendi\Cache\utils::get_request_object( 'COOKIE' ) );

        $this->assertNull( \Vendi\Cache\utils::get_request_object( 'FAKE' ) );
    }

    /**
     * @covers Vendi\Cache\utils::is_request_method
     */
    public function test_is_request_method()
    {
        $_SERVER[ 'REQUEST_METHOD' ] = 'POST';
        $this->assertTrue( \Vendi\Cache\utils::is_request_method( 'POST' ) );

        $_SERVER[ 'REQUEST_METHOD' ] = 'GET';
        $this->assertTrue( \Vendi\Cache\utils::is_request_method( 'GET' ) );

        $_SERVER[ 'REQUEST_METHOD' ] = 'HEAD';
        $this->assertTrue( \Vendi\Cache\utils::is_request_method( 'HEAD' ) );

        $_SERVER[ 'REQUEST_METHOD' ] = 'FAKE';
        $this->assertTrue( \Vendi\Cache\utils::is_request_method( 'FAKE' ) );

        $this->assertFalse( \Vendi\Cache\utils::is_request_method( 'POST' ) );
    }

    /**
     * @covers Vendi\Cache\utils::get_post_value_int
     * @covers Vendi\Cache\utils::get_request_value_int
     */
    public function test_get_post_value_int()
    {
        $_POST = array( 'a' => '1', 'b' => '1.1', 'c' => 'cheese' );

        $this->assertSame( 1, Vendi\Cache\utils::get_post_value_int( 'a' ) );
        $this->assertNull( Vendi\Cache\utils::get_post_value_int( 'b' ) );
        $this->assertNull( Vendi\Cache\utils::get_post_value_int( 'c' ) );
    }

    /**
     * @covers Vendi\Cache\utils::get_get_value_int
     * @covers Vendi\Cache\utils::get_request_value_int
     */
    public function test_get_get_value_int()
    {
        $_GET = array( 'a' => '1', 'b' => '1.1', 'c' => 'cheese' );

        $this->assertSame( 1, Vendi\Cache\utils::get_get_value_int( 'a' ) );
        $this->assertNull( Vendi\Cache\utils::get_get_value_int( 'b' ) );
        $this->assertNull( Vendi\Cache\utils::get_get_value_int( 'c' ) );
    }

    /**
     * @covers Vendi\Cache\utils::get_server_value_int
     * @covers Vendi\Cache\utils::get_request_value_int
     */
    public function test_get_server_value_int()
    {
        $_SERVER = array( 'a' => '1', 'b' => '1.1', 'c' => 'cheese' );

        $this->assertSame( 1, Vendi\Cache\utils::get_server_value_int( 'a' ) );
        $this->assertNull( Vendi\Cache\utils::get_server_value_int( 'b' ) );
        $this->assertNull( Vendi\Cache\utils::get_server_value_int( 'c' ) );
    }

    /**
     * @covers Vendi\Cache\utils::get_cookie_value_int
     * @covers Vendi\Cache\utils::get_request_value_int
     */
    public function test_get_cookie_value_int()
    {
        $_COOKIE = array( 'a' => '1', 'b' => '1.1', 'c' => 'cheese' );

        $this->assertSame( 1, Vendi\Cache\utils::get_cookie_value_int( 'a' ) );
        $this->assertNull( Vendi\Cache\utils::get_cookie_value_int( 'b' ) );
        $this->assertNull( Vendi\Cache\utils::get_cookie_value_int( 'c' ) );
    }
}
