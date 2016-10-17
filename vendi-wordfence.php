<?php
/*
Plugin Name: Vendi - Wordfence Caching
Plugin URI: http://www.wordfence.com/
Author: Wordfence
Version: 6.2.2
Author URI: http://www.wordfence.com/
Network: true
*/
if(defined('WP_INSTALLING') && WP_INSTALLING){
    return;
}
define('WORDFENCE_VERSION', '6.2.2');
define('WORDFENCE_BASENAME', function_exists('plugin_basename') ? plugin_basename(__FILE__) :
    basename(dirname(__FILE__)) . '/' . basename(__FILE__));

global $wp_plugin_paths;
foreach ($wp_plugin_paths as $dir => $realdir) {
    if (strpos(__FILE__, $realdir) === 0) {
        define('WORDFENCE_FCPATH', $dir . '/' . basename(__FILE__));
        define('WORDFENCE_PATH', trailingslashit($dir));
        break;
    }
}
if (!defined('WORDFENCE_FCPATH')) {
    define('WORDFENCE_FCPATH', __FILE__);
    define('WORDFENCE_PATH', trailingslashit(dirname(WORDFENCE_FCPATH)));
}


if(get_option('wordfenceActivated') != 1){
    add_action('activated_plugin','wordfence_save_activation_error'); function wordfence_save_activation_error(){ update_option('wf_plugin_act_error',  ob_get_contents()); }
}

if((int) @ini_get('memory_limit') < 128){
    if(strpos(ini_get('disable_functions'), 'ini_set') === false){
        @ini_set('memory_limit', '128M'); //Some hosts have ini set at as little as 32 megs. 64 is the min sane amount of memory.
    }
}

require_once 'lib/wordfenceClass.php';
require_once 'lib/wfCache.php';
require_once 'lib/wfConfig.php';
require_once 'lib/wfUtils.php';
require_once 'lib/wfDB.php';
require_once 'lib/wfSchema.php';
\Vendi\Wordfence\Caching\wordfence::install_actions();
