<?php
/**
 * Facebook Feed - Custom Post Type
 * 
 * Registrerer centershop_fb_post CPT til importerede Facebook opslag
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_FB_Post_Type {
    
    /**
     * Post type name
     */
    const POST_TYPE = 'centershop_fb_post';
    
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
        add_action('init', array($this, 'register_post_type'));
    }
    
    /**
     * Register post type
     */
    public function register_post_type() {
        $labels = array(
            'name'               => __('Facebook Opslag', 'centershop_txtdomain'),
            'singular_name'      => __('Facebook Opslag', 'centershop_txtdomain'),
            'menu_name'          => __('Facebook Feed', 'centershop_txtdomain'),
            'all_items'          => __('Alle opslag', 'centershop_txtdomain'),
            'view_item'          => __('Se opslag', 'centershop_txtdomain'),
            'search_items'       => __('Søg i opslag', 'centershop_txtdomain'),
            'not_found'          => __('Ingen opslag fundet', 'centershop_txtdomain'),
        );
        
        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => false, // Vises i CenterShop menu
            'query_var'          => true,
            'rewrite'            => array('slug' => 'facebook'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'supports'           => array('title', 'editor', 'thumbnail'),
            'show_in_rest'       => true,
        );
        
        register_post_type(self::POST_TYPE, $args);
    }
    
    /**
     * Check if post already exists by Facebook ID
     */
    public static function post_exists($fb_post_id) {
        $existing = get_posts(array(
            'post_type' => self::POST_TYPE,
            'meta_key' => '_centershop_fb_post_id',
            'meta_value' => $fb_post_id,
            'posts_per_page' => 1
        ));
        
        return !empty($existing) ? $existing[0]->ID : false;
    }
    
    /**
     * Create post from Facebook data
     */
    public static function create_from_fb_data($fb_data, $shop_id = null) {
        // Check if already exists
        if (self::post_exists($fb_data['id'])) {
            return false;
        }
        
        $shop = $shop_id ? get_post($shop_id) : null;
        
        $post_data = array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $shop ? $shop->post_title : __('Facebook opslag', 'centershop_txtdomain'),
            'post_content' => isset($fb_data['message']) ? $fb_data['message'] : '',
            'post_date' => isset($fb_data['created_time']) ? date('Y-m-d H:i:s', strtotime($fb_data['created_time'])) : current_time('mysql')
        );
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return false;
        }
        
        // Save meta
        update_post_meta($post_id, '_centershop_fb_post_id', sanitize_text_field($fb_data['id']));
        update_post_meta($post_id, '_centershop_fb_permalink', esc_url_raw($fb_data['permalink_url'] ?? ''));
        update_post_meta($post_id, '_centershop_fb_created_time', sanitize_text_field($fb_data['created_time'] ?? ''));
        
        if ($shop_id) {
            update_post_meta($post_id, '_centershop_shop_id', $shop_id);
        }
        
        // Handle image
        if (!empty($fb_data['full_picture'])) {
            update_post_meta($post_id, '_centershop_fb_image_url', esc_url_raw($fb_data['full_picture']));
            
            // Optionally download and attach image
            // self::attach_image_from_url($post_id, $fb_data['full_picture']);
        }
        
        return $post_id;
    }
    
    /**
     * Attach image from URL
     */
    private static function attach_image_from_url($post_id, $url) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $tmp = download_url($url);
        
        if (is_wp_error($tmp)) {
            return false;
        }
        
        $file_array = array(
            'name' => basename($url),
            'tmp_name' => $tmp
        );
        
        $attachment_id = media_handle_sideload($file_array, $post_id);
        
        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            return false;
        }
        
        set_post_thumbnail($post_id, $attachment_id);
        
        return $attachment_id;
    }
}
