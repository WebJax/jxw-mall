<?php
/**
 * Instagram Feed - Posts List
 * 
 * Admin liste til Instagram posts
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_IG_Posts_List {
    
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
        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_filter('manage_' . CenterShop_IG_Post_Type::POST_TYPE . '_posts_columns', array($this, 'set_columns'));
        add_action('manage_' . CenterShop_IG_Post_Type::POST_TYPE . '_posts_custom_column', array($this, 'render_column'), 10, 2);
    }
    
    /**
     * Add submenu page
     */
    public function add_submenu_page() {
        add_submenu_page(
            'centershop',
            __('Instagram Posts', 'centershop_txtdomain'),
            __('Instagram Posts', 'centershop_txtdomain'),
            'edit_posts',
            'edit.php?post_type=' . CenterShop_IG_Post_Type::POST_TYPE
        );
    }
    
    /**
     * Set custom columns
     */
    public function set_columns($columns) {
        $new_columns = array();
        
        $new_columns['cb'] = $columns['cb'];
        $new_columns['thumbnail'] = __('Billede', 'centershop_txtdomain');
        $new_columns['title'] = __('Titel', 'centershop_txtdomain');
        $new_columns['shop'] = __('Butik', 'centershop_txtdomain');
        $new_columns['media_type'] = __('Type', 'centershop_txtdomain');
        $new_columns['ig_date'] = __('Instagram Dato', 'centershop_txtdomain');
        $new_columns['date'] = __('Importeret', 'centershop_txtdomain');
        
        return $new_columns;
    }
    
    /**
     * Render custom column
     */
    public function render_column($column, $post_id) {
        switch ($column) {
            case 'thumbnail':
                $media_url = get_post_meta($post_id, '_centershop_ig_media_url', true);
                if ($media_url) {
                    echo '<img src="' . esc_url($media_url) . '" style="max-width: 60px; height: auto;">';
                } elseif (has_post_thumbnail($post_id)) {
                    echo get_the_post_thumbnail($post_id, array(60, 60));
                }
                break;
                
            case 'shop':
                $shop_id = get_post_meta($post_id, '_centershop_shop_id', true);
                if ($shop_id) {
                    $shop = get_post($shop_id);
                    if ($shop) {
                        echo '<a href="' . get_edit_post_link($shop_id) . '">' . esc_html($shop->post_title) . '</a>';
                    }
                }
                break;
                
            case 'media_type':
                $media_type = get_post_meta($post_id, '_centershop_ig_media_type', true);
                if ($media_type) {
                    echo '<span class="dashicons dashicons-' . ($media_type === 'VIDEO' ? 'video-alt3' : 'format-image') . '"></span> ';
                    echo esc_html(ucfirst(strtolower($media_type)));
                }
                break;
                
            case 'ig_date':
                $ig_date = get_post_meta($post_id, '_centershop_ig_timestamp', true);
                if ($ig_date) {
                    echo esc_html(date_i18n(get_option('date_format'), strtotime($ig_date)));
                }
                break;
        }
    }
}
