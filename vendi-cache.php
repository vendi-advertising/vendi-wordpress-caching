<?php
/*
Plugin Name: Vendi Cache
Description: Disk-based page and post cache. (Formerly Wordfence Falcon Cache)
Plugin URI: https://www.vendiadvertising.com/
Author: Vendi Advertising (Chris Haas)
Version: 1.2.0
Author URI: https://www.vendiadvertising.com/
Text Domain: vendi-cache
Domain Path: /languages
Stable tag: 1.2.0
*/

if( defined( 'WP_INSTALLING' ) && WP_INSTALLING )
{
    return;
}

//Shortcuts to the root of the plugin for various formats
define( 'VENDI_CACHE_FILE', __FILE__ );
define( 'VENDI_CACHE_URL', plugin_dir_url( __FILE__ ) );

//For launch we can't support MU however we're not going to remove the code for it.
define( 'VENDI_CACHE_SUPPORT_MU', false );

define( 'VENDI_CACHE_VERSION', '1.1.5' );

define( 'VENDI_CACHE_OPTION_KEY_FOR_ACTIVATION',    'vendiWordPressCachingActivated' );
define( 'VENDI_CACHE_OPTION_KEY_FOR_VERSION',       'vendi_cache_version' );
define( 'VENDI_CACHE_OPTION_KEY_ACTIVATION_ERROR',  'vendi_cache_plugin_act_error' );
define( 'VENDI_CACHE_ACTION_NAME_CACHE_CLEAR',      'vendi_cache_cache_clear');
define( 'VENDI_CACHE_PLUGIN_PAGE_SLUG',             'vendi-cache' );

//If you want to change the folder that items are cache to. Only lowercase letters and underscores are allowed.
//If an invalid pattern is detected it will be cleansed and possibly reverted back to vendi_cache.
if( ! defined( 'VENDI_CACHE_FOLDER_NAME' ) )
{
    define( 'VENDI_CACHE_FOLDER_NAME', 'vendi_cache');
}

//If you want to (for some reason) white-label this
if( ! defined( 'VENDI_CACHE_PLUGIN_NAME' ) )
{
    define( 'VENDI_CACHE_PLUGIN_NAME', 'Vendi Cache' );
}

//This code is original to WF and I'm pretty sure it allows a
//plugin to be hosted in a shared location on a server instead
//of installing it on every single site.
global $wp_plugin_paths;
foreach( $wp_plugin_paths as $dir => $realdir )
{
    if( 0 === strpos( __FILE__, $realdir ) )
    {
        define( 'VENDI_CACHE_FCPATH', $dir . '/' . basename( __FILE__ ) );
        define( 'VENDI_CACHE_PATH', trailingslashit( $dir ) );
        break;
    }
}

if( ! defined( 'VENDI_CACHE_FCPATH' ) )
{
    define( 'VENDI_CACHE_FCPATH', __FILE__ );
    define( 'VENDI_CACHE_PATH', trailingslashit( dirname( VENDI_CACHE_FCPATH ) ) );
}

if( 1 != get_option( VENDI_CACHE_OPTION_KEY_FOR_ACTIVATION ) )
{
    add_action(
                'activated_plugin',
                function()
                {
                    update_option( VENDI_CACHE_OPTION_KEY_ACTIVATION_ERROR, ob_get_contents() );
                }
            );
}

add_action(
            'plugins_loaded',
            function()
            {
                load_plugin_textdomain( 'vendi-cache', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
            }
        );

//Load both the legacy code as well as the new code
require_once VENDI_CACHE_PATH . '/autoload.php';

//Init
\Vendi\Cache\Legacy\wordfence::install_actions();
