<?php
/**
 * Instagram Feed - Database
 * 
 * Database operations for Instagram posts
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_IG_Database {
    
    /**
     * Table name
     */
    const TABLE_NAME = 'centershop_instagram_posts';
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Constructor
    }
    
    /**
     * Get table name with prefix
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }
    
    /**
     * Create table
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ig_id varchar(255) NOT NULL,
            shop_id bigint(20) NOT NULL,
            caption text,
            media_type varchar(50),
            media_url text,
            thumbnail_url text,
            permalink text,
            timestamp datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ig_id (ig_id),
            KEY shop_id (shop_id),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Insert post
     */
    public function insert_post($data) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $wpdb->insert(
            $table_name,
            array(
                'ig_id' => $data['ig_id'],
                'shop_id' => $data['shop_id'],
                'caption' => $data['caption'] ?? '',
                'media_type' => $data['media_type'] ?? '',
                'media_url' => $data['media_url'] ?? '',
                'thumbnail_url' => $data['thumbnail_url'] ?? '',
                'permalink' => $data['permalink'] ?? '',
                'timestamp' => $data['timestamp'] ?? current_time('mysql')
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update post
     */
    public function update_post($ig_id, $data) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $wpdb->update(
            $table_name,
            array(
                'caption' => $data['caption'] ?? '',
                'media_type' => $data['media_type'] ?? '',
                'media_url' => $data['media_url'] ?? '',
                'thumbnail_url' => $data['thumbnail_url'] ?? '',
                'permalink' => $data['permalink'] ?? '',
                'timestamp' => $data['timestamp'] ?? current_time('mysql')
            ),
            array('ig_id' => $ig_id),
            array('%s', '%s', '%s', '%s', '%s', '%s'),
            array('%s')
        );
        
        return $wpdb->rows_affected;
    }
    
    /**
     * Get post by IG ID
     */
    public function get_post_by_ig_id($ig_id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE ig_id = %s", $ig_id)
        );
    }
    
    /**
     * Delete old posts
     */
    public function delete_old_posts($days = 90) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $date = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        return $wpdb->query(
            $wpdb->prepare("DELETE FROM $table_name WHERE timestamp < %s", $date)
        );
    }
}
