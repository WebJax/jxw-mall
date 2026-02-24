<?php
/**
 * Instagram Feed - Importer
 * 
 * Importerer Instagram posts til WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_IG_Importer {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * API Handler
     */
    private $api;
    
    /**
     * Database
     */
    private $db;
    
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
        $this->api = CenterShop_IG_API_Handler::get_instance();
        $this->db = CenterShop_IG_Database::get_instance();
        
        add_action('centershop_ig_import_cron', array($this, 'import_all_shops'));
        add_action('wp_ajax_centershop_ig_import_now', array($this, 'ajax_import_now'));
    }
    
    /**
     * Import posts for all shops
     */
    public function import_all_shops() {
        $shops = get_posts(array(
            'post_type' => 'butiksside',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        $total_imported = 0;
        $errors = array();
        
        foreach ($shops as $shop) {
            $result = $this->import_shop_posts($shop->ID);
            
            if (is_wp_error($result)) {
                $errors[] = sprintf(
                    '%s: %s',
                    $shop->post_title,
                    $result->get_error_message()
                );
            } else {
                $total_imported += $result;
            }
        }
        
        // Log results
        if (!empty($errors)) {
            error_log('CenterShop Instagram Import Errors: ' . implode('; ', $errors));
        }
        
        update_option('centershop_ig_last_import', array(
            'time' => current_time('mysql'),
            'imported' => $total_imported,
            'errors' => $errors
        ));
        
        return $total_imported;
    }
    
    /**
     * Import posts for a specific shop
     */
    public function import_shop_posts($shop_id, $limit = 10) {
        $ig_user_id = get_post_meta($shop_id, 'butik_payed_ig_user_id', true);
        $ig_token = get_post_meta($shop_id, 'butik_payed_ig_token', true);
        
        // Skip if no Instagram configured
        if (empty($ig_user_id) || empty($ig_token)) {
            return 0;
        }
        
        // Get last import time
        $last_import = get_post_meta($shop_id, '_centershop_ig_last_import', true);
        $since = $last_import ? strtotime($last_import) : null;
        
        // Fetch posts from Instagram
        $posts = $this->api->get_user_media($ig_user_id, $limit, $since, $ig_token);
        
        if (is_wp_error($posts)) {
            return $posts;
        }
        
        $imported = 0;
        
        foreach ($posts as $ig_post) {
            $result = $this->import_post($ig_post, $shop_id);
            if ($result) {
                $imported++;
            }
        }
        
        // Update last import time
        update_post_meta($shop_id, '_centershop_ig_last_import', current_time('mysql'));
        
        return $imported;
    }
    
    /**
     * Import single post
     */
    private function import_post($ig_post, $shop_id) {
        $ig_id = $ig_post['id'];
        
        // Check if post already exists
        $existing_posts = get_posts(array(
            'post_type' => CenterShop_IG_Post_Type::POST_TYPE,
            'meta_key' => '_centershop_ig_id',
            'meta_value' => $ig_id,
            'posts_per_page' => 1
        ));
        
        // Get media URL (use thumbnail for videos)
        $media_url = $ig_post['media_url'] ?? '';
        if ($ig_post['media_type'] === 'VIDEO' && !empty($ig_post['thumbnail_url'])) {
            $media_url = $ig_post['thumbnail_url'];
        }
        
        // Prepare post data
        $post_data = array(
            'post_type' => CenterShop_IG_Post_Type::POST_TYPE,
            'post_title' => wp_trim_words($ig_post['caption'] ?? 'Instagram Post', 10),
            'post_content' => $ig_post['caption'] ?? '',
            'post_status' => 'publish',
            'post_date' => date('Y-m-d H:i:s', strtotime($ig_post['timestamp']))
        );
        
        if (!empty($existing_posts)) {
            // Update existing post
            $post_data['ID'] = $existing_posts[0]->ID;
            $post_id = wp_update_post($post_data);
        } else {
            // Create new post
            $post_id = wp_insert_post($post_data);
        }
        
        if (is_wp_error($post_id) || !$post_id) {
            return false;
        }
        
        // Update meta data
        update_post_meta($post_id, '_centershop_ig_id', $ig_id);
        update_post_meta($post_id, '_centershop_shop_id', $shop_id);
        update_post_meta($post_id, '_centershop_ig_media_url', $media_url);
        update_post_meta($post_id, '_centershop_ig_permalink', $ig_post['permalink'] ?? '');
        update_post_meta($post_id, '_centershop_ig_timestamp', $ig_post['timestamp'] ?? '');
        update_post_meta($post_id, '_centershop_ig_media_type', $ig_post['media_type'] ?? '');
        
        // Download and set featured image if available
        if (!empty($media_url)) {
            $this->set_featured_image($post_id, $media_url);
        }
        
        return true;
    }
    
    /**
     * Set featured image from URL
     */
    private function set_featured_image($post_id, $image_url) {
        // Check if already has featured image
        if (get_post_thumbnail_id($post_id)) {
            return;
        }
        
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $image_id = media_sideload_image($image_url, $post_id, null, 'id');
        
        if (!is_wp_error($image_id)) {
            set_post_thumbnail($post_id, $image_id);
        }
    }
    
    /**
     * AJAX import now
     */
    public function ajax_import_now() {
        check_ajax_referer('centershop_ig_import', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $result = $this->import_all_shops();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('%d posts importeret', 'centershop_txtdomain'), $result)
        ));
    }
}
