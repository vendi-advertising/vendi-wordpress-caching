<?php
/*
Plugin Name: Vendi - Wordfence Caching
Plugin URI: http://www.wordfence.com/
Author: Wordfence
Version: 6.2.2
Author URI: http://www.wordfence.com/
Network: true
*/

if( defined( 'WP_INSTALLING' ) && WP_INSTALLING )
{
    return;
}

//Shortcuts to the root of the plugin for various formats
define( 'VENDI_WORDPRESS_CACHING_FILE', __FILE__ );
define( 'VENDI_WORDPRESS_CACHING_PATH', dirname( __FILE__ ) );
define( 'VENDI_WORDPRESS_CACHING_URL',  plugin_dir_url( __FILE__ ) );


define( 'WORDFENCE_VERSION', '6.2.2' );

//I'm pretty sure this has to do with allowing the plugin to be hosted outside
//of the normal location.
global $wp_plugin_paths;
foreach ($wp_plugin_paths as $dir => $realdir)
{
    if ( 0 === strpos( __FILE__, $realdir ) )
    {
        define( 'WORDFENCE_FCPATH', $dir . '/' . basename( __FILE__ ) );
        define( 'WORDFENCE_PATH', trailingslashit( $dir ) );
        break;
    }
}

if ( ! defined( 'WORDFENCE_FCPATH' ) )
{
    define( 'WORDFENCE_FCPATH', __FILE__ );
    define( 'WORDFENCE_PATH', trailingslashit( dirname( WORDFENCE_FCPATH ) ) );
}

if ( 1 != get_option( 'wordfenceActivated' ) )
{
    add_action(
                'activated_plugin',
                function()
                {
                    update_option( 'wf_plugin_act_error',  ob_get_contents() );
                }
            );
}

if ( (int) @ini_get( 'memory_limit' ) < 128 )
{
    if ( false === strpos( ini_get( 'disable_functions' ), 'ini_set' ) )
    {
        @ini_set( 'memory_limit', '128M' ); //Some hosts have ini set at as little as 32 megs. 64 is the min sane amount of memory.
    }
}


//Load both the legacy code as well as the new code
require_once VENDI_WORDPRESS_CACHING_PATH . '/autoload.php';

//Init
\Vendi\Wordfence\Caching\wordfence::install_actions();
