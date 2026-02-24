<?php
/**
 * Instagram Feed - Post Type
 * 
 * Registrerer custom post type til Instagram posts
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_IG_Post_Type {
    
    /**
     * Post type slug
     */
    const POST_TYPE = 'ig_post';
    
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
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
    }
    
    /**
     * Register post type
     */
    public function register_post_type() {
        $labels = array(
            'name' => __('Instagram Posts', 'centershop_txtdomain'),
            'singular_name' => __('Instagram Post', 'centershop_txtdomain'),
            'menu_name' => __('Instagram Feed', 'centershop_txtdomain'),
            'all_items' => __('Alle Posts', 'centershop_txtdomain'),
            'view_item' => __('Se Post', 'centershop_txtdomain'),
            'search_items' => __('Søg Posts', 'centershop_txtdomain'),
            'not_found' => __('Ingen posts fundet', 'centershop_txtdomain'),
            'not_found_in_trash' => __('Ingen posts i papirkurv', 'centershop_txtdomain')
        );
        
        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false, // Will be added as submenu
            'show_in_rest' => true,
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => null,
            'menu_icon' => 'dashicons-instagram',
            'supports' => array('title', 'editor', 'thumbnail'),
            'rewrite' => false,
            'query_var' => false
        );
        
        register_post_type(self::POST_TYPE, $args);
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'centershop_ig_post_details',
            __('Instagram Post Detaljer', 'centershop_txtdomain'),
            array($this, 'render_meta_box'),
            self::POST_TYPE,
            'side',
            'default'
        );
    }
    
    /**
     * Render meta box
     */
    public function render_meta_box($post) {
        $ig_id = get_post_meta($post->ID, '_centershop_ig_id', true);
        $ig_url = get_post_meta($post->ID, '_centershop_ig_permalink', true);
        $ig_date = get_post_meta($post->ID, '_centershop_ig_timestamp', true);
        $media_type = get_post_meta($post->ID, '_centershop_ig_media_type', true);
        $shop_id = get_post_meta($post->ID, '_centershop_shop_id', true);
        $shop = $shop_id ? get_post($shop_id) : null;
        
        ?>
        <div class="centershop-ig-meta">
            <?php if ($shop): ?>
                <p>
                    <strong><?php _e('Butik:', 'centershop_txtdomain'); ?></strong><br>
                    <?php echo esc_html($shop->post_title); ?>
                </p>
            <?php endif; ?>
            
            <?php if ($media_type): ?>
                <p>
                    <strong><?php _e('Medie Type:', 'centershop_txtdomain'); ?></strong><br>
                    <?php echo esc_html(ucfirst($media_type)); ?>
                </p>
            <?php endif; ?>
            
            <?php if ($ig_date): ?>
                <p>
                    <strong><?php _e('Instagram Dato:', 'centershop_txtdomain'); ?></strong><br>
                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($ig_date))); ?>
                </p>
            <?php endif; ?>
            
            <?php if ($ig_id): ?>
                <p>
                    <strong><?php _e('Instagram ID:', 'centershop_txtdomain'); ?></strong><br>
                    <code><?php echo esc_html($ig_id); ?></code>
                </p>
            <?php endif; ?>
            
            <?php if ($ig_url): ?>
                <p>
                    <a href="<?php echo esc_url($ig_url); ?>" target="_blank" class="button button-secondary">
                        <?php _e('Se på Instagram', 'centershop_txtdomain'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
}
