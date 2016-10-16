<?php

namespace Vendi\Wordfence\Caching;

class wfSchema {
    private $tables = array(
"wfConfig" => "(
    name varchar(100) PRIMARY KEY NOT NULL,
    val longblob
) default charset=utf8",
);
    private $db = false;
    private $prefix = 'wp_';
    public function __construct($dbhost = false, $dbuser = false, $dbpassword = false, $dbname = false){
        /*
        if($dbhost){ //for testing
            $this->db = new wfDB(false, $dbhost, $dbuser, $dbpassword, $dbname);
            $this->prefix = 'wp_';
        } else {
        */
        global $wpdb;
        $this->db = new wfDB();
        $this->prefix = $wpdb->base_prefix;
    }
    public function dropAll(){
        foreach($this->tables as $table => $def){
            $this->db->queryWrite("drop table if exists " . $this->prefix . $table);
        }
    }
    public function createAll(){
        foreach($this->tables as $table => $def){
            $this->db->queryWrite("create table IF NOT EXISTS " . $this->prefix . $table . " " . $def);
        }
    }
    public function create($table){
        $this->db->queryWrite("create table IF NOT EXISTS " . $this->prefix . $table . " " . $this->tables[$table]);
    }
    public function drop($table){
        $this->db->queryWrite("drop table if exists " . $this->prefix . $table);
    }
}
?>
