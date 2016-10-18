<?php

namespace Vendi\WordPress\Caching\Legacy;

class wfConfig {
    const AUTOLOAD = 'yes';
    const DONT_AUTOLOAD = 'no';
    
    public static $diskCache = array();
    private static $diskCacheDisabled = false; //enables if we detect a write fail so we don't keep calling stat()
    private static $cacheDisableCheckDone = false;
    private static $table = false;
    private static $tableExists = true;
    private static $cache = array();
    private static $DB = false;
    private static $tmpFileHeader = "<?php\n/* Wordfence temporary file security header */\necho \"Nothing to see here!\\n\"; exit(0);\n?>";
    private static $tmpDirCache = false;
    public static $defaultConfig = array(
        "checkboxes" => array(
            "addCacheComment" => array('value' => false, 'autoload' => self::AUTOLOAD),
            "allowHTTPSCaching" => array('value' => false, 'autoload' => self::AUTOLOAD),
        ),
        "otherParams" => array(
            'actUpdateInterval' => '',
        )
    );
    public static $serializedOptions = array('lastAdminLogin', 'scanSched', 'emailedIssuesList', 'wf_summaryItems', 'adminUserList', 'twoFactorUsers', 'alertFreqTrack', 'wfStatusStartMsgs');
    /**
     * .htaccess file contents to disable all script execution in a given directory.
     */
    private static $_disable_scripts_htaccess = '# BEGIN Wordfence code execution protection
<IfModule mod_php5.c>
php_flag engine 0
</IfModule>
<IfModule mod_php7.c>
php_flag engine 0
</IfModule>

AddHandler cgi-script .php .phtml .php3 .pl .py .jsp .asp .htm .shtml .sh .cgi
Options -ExecCGI
# END Wordfence code execution protection
';
    private static $_disable_scripts_regex = '/# BEGIN Wordfence code execution protection.+?# END Wordfence code execution protection/s';
    public static function setDefaults() {
        foreach (self::$defaultConfig['checkboxes'] as $key => $config) {
            $val = $config['value'];
            $autoload = $config['autoload'];
            if (self::get($key) === false) {
                self::set($key, $val ? '1' : '0', $autoload);
            }
        }
        foreach (self::$defaultConfig['otherParams'] as $key => $val) {
            if (self::get($key) === false) {
                self::set($key, $val);
            }
        }
        if (self::get('maxMem', false) === false) {
            self::set('maxMem', '256');
        }
    }
    public static function loadAllOptions() {
        global $wpdb;
        
        $options = wp_cache_get('alloptions', 'wordfence');
        if (!$options) {
            $table = self::table();
            self::updateTableExists();
            $suppress = $wpdb->suppress_errors();
            if (!($rawOptions = $wpdb->get_results("SELECT name, val FROM {$table} WHERE autoload = 'yes'"))) {
                $rawOptions = $wpdb->get_results("SELECT name, val FROM {$table}");
            }
            $wpdb->suppress_errors($suppress);
            $options = array();
            foreach ((array) $rawOptions as $o) {
                if (in_array($o->name, self::$serializedOptions)) {
                    $val = maybe_unserialize($o->val);
                    if ($val) {
                        $options[$o->name] = $val;
                    }
                }
                else {
                    $options[$o->name] = $o->val;
                }
            }
            
            wp_cache_add_non_persistent_groups('wordfence');
            wp_cache_add('alloptions', $options, 'wordfence');
        }
        
        return $options;
    }
    public static function updateTableExists() {
        $table = self::table();
        self::$tableExists = (strtolower(self::getDB()->querySingle("SHOW TABLES LIKE '%s'", $table)) == strtolower($table));
    }
    private static function updateCachedOption($name, $val) {
        $options = self::loadAllOptions();
        $options[$name] = $val;
        wp_cache_set('alloptions', $options, 'wordfence');
    }
    private static function removeCachedOption($name) {
        $options = self::loadAllOptions();
        if (isset($options[$name])) {
            unset($options[$name]);
            wp_cache_set('alloptions', $options, 'wordfence');
        }
    }
    private static function getCachedOption($name) {
        $options = self::loadAllOptions();
        if (isset($options[$name])) {
            return $options[$name];
        }
        
        $table = self::table();
        $val = self::getDB()->querySingle("SELECT val FROM {$table} WHERE name='%s'", $name);
        if ($val !== null) {
            $options[$name] = $val;
            wp_cache_set('alloptions', $options, 'wordfence');
        }
        return $val;
    }
    private static function hasCachedOption($name) {
        $options = self::loadAllOptions();
        return isset($options[$name]);
    }
    public static function parseOptions(){
        $ret = array();
        foreach(self::$defaultConfig['checkboxes'] as $key => $val){ //value is not used. We just need the keys for validation
            $ret[$key] = isset($_POST[$key]) ? '1' : '0';
        }
        foreach(self::$defaultConfig['otherParams'] as $key => $val){
            if(isset($_POST[$key])){
                $ret[$key] = stripslashes($_POST[$key]);
            } else {
                error_log("Missing options param \"$key\" when parsing parameters.");
            }
        }
        return $ret;
    }
    public static function set($key, $val, $autoload = self::AUTOLOAD) {
        global $wpdb;
        
        if (is_array($val)) {
            $msg = "wfConfig::set() got an array as second param with key: $key and value: " . var_export($val, true);
            wordfence::status(1, 'error', $msg);
            return;
        }
        
        if (!self::$tableExists) {
            return;
        }
        
        $table = self::table();
        if ($wpdb->query($wpdb->prepare("INSERT INTO {$table} (name, val, autoload) values (%s, %s, %s) ON DUPLICATE KEY UPDATE val = %s, autoload = %s", $key, $val, $autoload, $val, $autoload)) !== false && $autoload != self::DONT_AUTOLOAD) {
            self::updateCachedOption($key, $val);
        }
    }
    public static function get($key, $default = false) {
        global $wpdb;
        
        if (self::hasCachedOption($key)) {
            return self::getCachedOption($key);
        }
        
        if (!self::$tableExists) {
            return $default;
        }
        
        $table = self::table();
        if (!($option = $wpdb->get_row($wpdb->prepare("SELECT name, val, autoload FROM {$table} WHERE name = %s", $key)))) {
            return $default;
        }
        
        if ($option->autoload != self::DONT_AUTOLOAD) {
            self::updateCachedOption($key, $option->val);
        }
        return $option->val;
    }
    
    private static function canCompressValue() {
        if (!function_exists('gzencode') || !function_exists('gzdecode')) {
            return false;
        }
        $disabled = explode(',', ini_get('disable_functions'));
        if (in_array('gzencode', $disabled) || in_array('gzdecode', $disabled)) {
            return false;
        }
        return true;
    }
    
    private static function isCompressedValue($data) {
        //Based on http://www.ietf.org/rfc/rfc1952.txt
        if (strlen($data) < 2) {
            return false;
        }
        
        $magicBytes = substr($data, 0, 2);
        if ($magicBytes !== (chr(0x1f) . chr(0x8b))) {
            return false;
        }
        
        //Small chance of false positives here -- can check the header CRC if it turns out it's needed
        return true;
    }
    
    private static function ser_chunked_key($key) {
        return 'wordfence_chunked_' . $key . '_';
    }
    public static function cb($key){
        if(self::get($key)){
            echo ' checked ';
        }
    }
    private static function getDB(){
        if(! self::$DB){ 
            self::$DB = new wfDB();
        }
        return self::$DB;
    }
    private static function table(){
        if(! self::$table){
            global $wpdb;
            self::$table = $wpdb->base_prefix . 'wfConfig';
        }
        return self::$table;
    }

    private static function _uploadsHtaccessFilePath() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/.htaccess';
    }

    /**
     * Add/Merge .htaccess file in the uploads directory to prevent code execution.
     *
     * @return bool
     * @throws wfConfigException
     */
    public static function disableCodeExecutionForUploads() {
        $uploads_htaccess_file_path = self::_uploadsHtaccessFilePath();
        $uploads_htaccess_has_content = false;
        if (file_exists($uploads_htaccess_file_path)) {
            $htaccess_contents = file_get_contents($uploads_htaccess_file_path);
            
            // htaccess exists and contains our htaccess code to disable script execution, nothing more to do
            if (strpos($htaccess_contents, self::$_disable_scripts_htaccess) !== false) {
                return true;
            }
            $uploads_htaccess_has_content = strlen(trim($htaccess_contents)) > 0;
        }
        if (@file_put_contents($uploads_htaccess_file_path, ($uploads_htaccess_has_content ? "\n\n" : "") . self::$_disable_scripts_htaccess, FILE_APPEND | LOCK_EX) === false) {
            throw new wfConfigException("Unable to save the .htaccess file needed to disable script execution in the uploads directory.  Please check your permissions on that directory.");
        }
        self::set('disableCodeExecutionUploadsPHP7Migrated', true);
        return true;
    }
    
    public static function migrateCodeExecutionForUploadsPHP7() {
        if (self::get('disableCodeExecutionUploads')) {
            if (!self::get('disableCodeExecutionUploadsPHP7Migrated')) {
                $uploads_htaccess_file_path = self::_uploadsHtaccessFilePath();
                if (file_exists($uploads_htaccess_file_path)) {
                    $htaccess_contents = file_get_contents($uploads_htaccess_file_path);
                    if (preg_match(self::$_disable_scripts_regex, $htaccess_contents)) {
                        $htaccess_contents = preg_replace(self::$_disable_scripts_regex, self::$_disable_scripts_htaccess, $htaccess_contents); 
                        @file_put_contents($uploads_htaccess_file_path, $htaccess_contents);
                        self::set('disableCodeExecutionUploadsPHP7Migrated', true);
                    }
                }
            }
        }
    }

    /**
     * Remove script execution protections for our the .htaccess file in the uploads directory.
     *
     * @return bool
     * @throws wfConfigException
     */
    public static function removeCodeExecutionProtectionForUploads() {
        $uploads_htaccess_file_path = self::_uploadsHtaccessFilePath();
        if (file_exists($uploads_htaccess_file_path)) {
            $htaccess_contents = file_get_contents($uploads_htaccess_file_path);

            // Check that it is in the file
            if (preg_match(self::$_disable_scripts_regex, $htaccess_contents)) {
                $htaccess_contents = preg_replace(self::$_disable_scripts_regex, '', $htaccess_contents);

                $error_message = "Unable to remove code execution protections applied to the .htaccess file in the uploads directory.  Please check your permissions on that file.";
                if (strlen(trim($htaccess_contents)) === 0) {
                    // empty file, remove it
                    if (!@unlink($uploads_htaccess_file_path)) {
                        throw new wfConfigException($error_message);
                    }

                } elseif (@file_put_contents($uploads_htaccess_file_path, $htaccess_contents, LOCK_EX) === false) {
                    throw new wfConfigException($error_message);
                }
            }
        }
        return true;
    }
}

class wfConfigException extends \Exception {}
