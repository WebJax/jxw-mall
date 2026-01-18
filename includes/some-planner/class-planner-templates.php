<?php
/**
 * SoMe Planner - Templates
 * 
 * Håndterer caption templates og indholdsskabeloner
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_Planner_Templates {
    
    /**
     * Post type for templates
     */
    const POST_TYPE = 'centershop_template';
    
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
        add_action('save_post_' . self::POST_TYPE, array($this, 'save_meta'), 10, 2);
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    /**
     * Register post type
     */
    public function register_post_type() {
        $labels = array(
            'name'               => __('Skabeloner', 'centershop_txtdomain'),
            'singular_name'      => __('Skabelon', 'centershop_txtdomain'),
            'menu_name'          => __('Skabeloner', 'centershop_txtdomain'),
            'add_new'            => __('Tilføj ny', 'centershop_txtdomain'),
            'add_new_item'       => __('Tilføj ny skabelon', 'centershop_txtdomain'),
            'edit_item'          => __('Rediger skabelon', 'centershop_txtdomain'),
            'new_item'           => __('Ny skabelon', 'centershop_txtdomain'),
            'view_item'          => __('Se skabelon', 'centershop_txtdomain'),
            'search_items'       => __('Søg i skabeloner', 'centershop_txtdomain'),
            'not_found'          => __('Ingen skabeloner fundet', 'centershop_txtdomain'),
        );
        
        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'centershop',
            'capability_type'    => 'post',
            'has_archive'        => false,
            'supports'           => array('title'),
            'show_in_rest'       => true,
        );
        
        register_post_type(self::POST_TYPE, $args);
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'centershop_template_content',
            __('Skabelonindhold', 'centershop_txtdomain'),
            array($this, 'render_content_meta_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );
        
        add_meta_box(
            'centershop_template_guide',
            __('Medieguide', 'centershop_txtdomain'),
            array($this, 'render_guide_meta_box'),
            self::POST_TYPE,
            'normal',
            'default'
        );
    }
    
    /**
     * Render content meta box
     */
    public function render_content_meta_box($post) {
        wp_nonce_field('centershop_template_content', 'centershop_template_nonce');
        
        $caption_template = get_post_meta($post->ID, '_centershop_caption_template', true);
        
        ?>
        <p>
            <label for="centershop_caption_template"><strong><?php _e('Caption skabelon', 'centershop_txtdomain'); ?></strong></label>
        </p>
        <textarea id="centershop_caption_template" name="centershop_caption_template" 
                  class="large-text" rows="6"><?php echo esc_textarea($caption_template); ?></textarea>
        <p class="description">
            <?php _e('Brug variabler:', 'centershop_txtdomain'); ?>
            <code>{shop_name}</code>, <code>{shop_address}</code>, <code>{date}</code>
        </p>
        <?php
    }
    
    /**
     * Render guide meta box
     */
    public function render_guide_meta_box($post) {
        $media_guide = get_post_meta($post->ID, '_centershop_media_guide', true);
        
        ?>
        <textarea id="centershop_media_guide" name="centershop_media_guide" 
                  class="large-text" rows="4"><?php echo esc_textarea($media_guide); ?></textarea>
        <p class="description">
            <?php _e('Vejledning til hvilken type billede/video der passer til denne skabelon.', 'centershop_txtdomain'); ?>
        </p>
        <?php
    }
    
    /**
     * Save meta
     */
    public function save_meta($post_id, $post) {
        if (!isset($_POST['centershop_template_nonce']) || 
            !wp_verify_nonce($_POST['centershop_template_nonce'], 'centershop_template_content')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (isset($_POST['centershop_caption_template'])) {
            update_post_meta($post_id, '_centershop_caption_template', wp_kses_post($_POST['centershop_caption_template']));
        }
        
        if (isset($_POST['centershop_media_guide'])) {
            update_post_meta($post_id, '_centershop_media_guide', sanitize_textarea_field($_POST['centershop_media_guide']));
        }
    }
    
    /**
     * Register REST routes
     */
    public function register_rest_routes() {
        register_rest_route('centershop/v1', '/templates', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_templates'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
    }
    
    /**
     * REST: Get templates
     */
    public function rest_get_templates() {
        $templates = get_posts(array(
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        $result = array();
        foreach ($templates as $template) {
            $result[] = array(
                'id' => $template->ID,
                'name' => $template->post_title,
                'caption_template' => get_post_meta($template->ID, '_centershop_caption_template', true),
                'media_guide' => get_post_meta($template->ID, '_centershop_media_guide', true)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Apply template to post
     */
    public static function apply_template($template_id, $shop_id = null) {
        $template = get_post($template_id);
        if (!$template) {
            return '';
        }
        
        $caption = get_post_meta($template_id, '_centershop_caption_template', true);
        
        // Replace variables
        $shop = $shop_id ? get_post($shop_id) : null;
        
        $replacements = array(
            '{shop_name}' => $shop ? $shop->post_title : '',
            '{shop_address}' => $shop ? get_post_meta($shop_id, 'butik_payed_adress', true) : '',
            '{date}' => date_i18n('j. F Y'),
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $caption);
    }
}
