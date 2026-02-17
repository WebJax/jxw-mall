<?php
/**
 * Facebook Feed - Database Handler
 * 
 * Håndterer database tabel for Facebook posts
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_FB_Database {
    
    /**
     * Table name (without prefix)
     */
    const TABLE_NAME = 'centershop_fb_posts';
    
    /**
     * Database version
     */
    const DB_VERSION = '1.0';
    
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
        // Installation handled separately
    }
    
    /**
     * Get full table name with prefix
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }
    
    /**
     * Create database table
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            fb_post_id varchar(100) NOT NULL,
            fb_page_id varchar(100) NOT NULL,
            shop_id bigint(20) UNSIGNED DEFAULT NULL,
            message text DEFAULT NULL,
            permalink_url varchar(500) DEFAULT NULL,
            full_picture varchar(500) DEFAULT NULL,
            created_time datetime DEFAULT NULL,
            imported_time datetime DEFAULT CURRENT_TIMESTAMP,
            media_type varchar(50) DEFAULT NULL,
            media_url varchar(500) DEFAULT NULL,
            attachments_data longtext DEFAULT NULL,
            likes_count int DEFAULT 0,
            comments_count int DEFAULT 0,
            shares_count int DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY fb_post_id (fb_post_id),
            KEY fb_page_id (fb_page_id),
            KEY shop_id (shop_id),
            KEY created_time (created_time),
            KEY imported_time (imported_time)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        update_option('centershop_fb_db_version', self::DB_VERSION);
    }
    
    /**
     * Insert Facebook post
     */
    public function insert_post($fb_data, $fb_page_id, $shop_id = null) {
        global $wpdb;
        
        // Extract attachments data if present
        $attachments_json = null;
        if (isset($fb_data['attachments'])) {
            $attachments_json = wp_json_encode($fb_data['attachments']);
        }
        
        // Get media info from attachments
        $media_type = null;
        $media_url = null;
        if (isset($fb_data['attachments']['data'][0])) {
            $attachment = $fb_data['attachments']['data'][0];
            $media_type = $attachment['media_type'] ?? null;
            $media_url = $attachment['url'] ?? $attachment['media']['image']['src'] ?? null;
        }
        
        // Extract public engagement counts
        $likes_count = isset($fb_data['likes']['summary']['total_count']) 
            ? intval($fb_data['likes']['summary']['total_count']) : 0;
        $comments_count = isset($fb_data['comments']['summary']['total_count']) 
            ? intval($fb_data['comments']['summary']['total_count']) : 0;
        $shares_count = isset($fb_data['shares']['count']) 
            ? intval($fb_data['shares']['count']) : 0;
        
        $data = array(
            'fb_post_id' => sanitize_text_field($fb_data['id']),
            'fb_page_id' => sanitize_text_field($fb_page_id),
            'shop_id' => $shop_id ? intval($shop_id) : null,
            'message' => isset($fb_data['message']) ? wp_kses_post($fb_data['message']) : null,
            'permalink_url' => isset($fb_data['permalink_url']) ? esc_url_raw($fb_data['permalink_url']) : null,
            'full_picture' => isset($fb_data['full_picture']) ? esc_url_raw($fb_data['full_picture']) : null,
            'created_time' => isset($fb_data['created_time']) ? date('Y-m-d H:i:s', strtotime($fb_data['created_time'])) : null,
            'media_type' => $media_type ? sanitize_text_field($media_type) : null,
            'media_url' => $media_url ? esc_url_raw($media_url) : null,
            'attachments_data' => $attachments_json,
            'likes_count' => $likes_count,
            'comments_count' => $comments_count,
            'shares_count' => $shares_count,
            'imported_time' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            self::get_table_name(),
            $data,
            array('%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s')
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Check if post exists by Facebook ID
     */
    public function post_exists($fb_post_id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE fb_post_id = %s",
            $fb_post_id
        ));
        
        return $exists ? intval($exists) : false;
    }
    
    /**
     * Get posts by shop ID
     */
    public function get_posts_by_shop($shop_id, $limit = 10, $offset = 0) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE shop_id = %d 
             ORDER BY created_time DESC 
             LIMIT %d OFFSET %d",
            $shop_id,
            $limit,
            $offset
        ));
    }
    
    /**
     * Get posts by page ID
     */
    public function get_posts_by_page($fb_page_id, $limit = 10, $offset = 0) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE fb_page_id = %s 
             ORDER BY created_time DESC 
             LIMIT %d OFFSET %d",
            $fb_page_id,
            $limit,
            $offset
        ));
    }
    
    /**
     * Get all posts
     */
    public function get_all_posts($limit = 25, $offset = 0, $shop_id = null) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        if ($shop_id) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name 
                 WHERE shop_id = %d 
                 ORDER BY created_time DESC 
                 LIMIT %d OFFSET %d",
                $shop_id,
                $limit,
                $offset
            ));
        } else {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name 
                 ORDER BY created_time DESC 
                 LIMIT %d OFFSET %d",
                $limit,
                $offset
            ));
        }
    }
    
    /**
     * Get total post count
     */
    public function get_total_count($shop_id = null) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        if ($shop_id) {
            return $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE shop_id = %d",
                $shop_id
            ));
        } else {
            return $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        }
    }
    
    /**
     * Delete old posts
     */
    public function delete_old_posts($days_to_keep) {
        global $wpdb;
        
        if ($days_to_keep <= 0) {
            return 0;
        }
        
        $table_name = self::get_table_name();
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_time < %s",
            $cutoff_date
        ));
        
        return $deleted !== false ? $deleted : 0;
    }
    
    /**
     * Delete post by ID
     */
    public function delete_post($id) {
        global $wpdb;
        
        return $wpdb->delete(
            self::get_table_name(),
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Delete post by Facebook ID
     */
    public function delete_post_by_fb_id($fb_post_id) {
        global $wpdb;
        
        return $wpdb->delete(
            self::get_table_name(),
            array('fb_post_id' => $fb_post_id),
            array('%s')
        );
    }
    
    /**
     * Update post
     */
    public function update_post($id, $data) {
        global $wpdb;
        
        return $wpdb->update(
            self::get_table_name(),
            $data,
            array('id' => $id),
            null,
            array('%d')
        );
    }
    
    /**
     * Get post by ID
     */
    public function get_post($id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Get post by Facebook ID
     */
    public function get_post_by_fb_id($fb_post_id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE fb_post_id = %s",
            $fb_post_id
        ));
    }
    
    /**
     * Get recent posts (all shops combined)
     */
    public function get_recent_posts($limit = 10) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             ORDER BY created_time DESC 
             LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Search posts
     */
    public function search_posts($search_term, $limit = 25, $offset = 0) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $search_like = '%' . $wpdb->esc_like($search_term) . '%';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE message LIKE %s 
             ORDER BY created_time DESC 
             LIMIT %d OFFSET %d",
            $search_like,
            $limit,
            $offset
        ));
    }
}
