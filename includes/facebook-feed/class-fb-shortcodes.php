<?php
/**
 * Facebook Feed - Shortcodes
 * 
 * Shortcodes til at vise Facebook feed på frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_FB_Shortcodes {
    
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
        add_shortcode('centershop_facebook_feed', array($this, 'render_shortcode'));
        add_shortcode('mall_facebook_feed', array($this, 'render_shortcode')); // Alias
        add_action('wp_enqueue_scripts', array($this, 'register_styles'));
    }
    
    /**
     * Register frontend styles
     */
    public function register_styles() {
        wp_register_style(
            'centershop-fb-feed',
            CENTERSHOP_PLUGIN_URL . 'css/centershop-fb-feed.css',
            array(),
            filemtime(plugin_dir_path(__FILE__) . '../css/centershop-fb-feed.css')
        );
    }
    
    /**
     * Render shortcode
     * 
     * Usage: [centershop_facebook_feed count="10" shop="123" layout="grid"]
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'count' => 10,
            'shop' => '',
            'layout' => 'grid', // grid, list
            'columns' => 3,
            'show_date' => 'yes',
            'show_shop' => 'yes',
            'excerpt_length' => 150
        ), $atts, 'centershop_facebook_feed');
        
        // Query args
        $args = array(
            'post_type' => CenterShop_FB_Post_Type::POST_TYPE,
            'posts_per_page' => intval($atts['count']),
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        // Filter by shop
        if (!empty($atts['shop'])) {
            $args['meta_query'] = array(
                array(
                    'key' => '_centershop_shop_id',
                    'value' => intval($atts['shop']),
                    'compare' => '='
                )
            );
        }
        
        $posts = get_posts($args);
        
        if (empty($posts)) {
            return '<p class="centershop-fb-no-posts">' . __('Ingen opslag fundet.', 'centershop_txtdomain') . '</p>';
        }
        
        wp_enqueue_style('centershop-fb-feed');
        
        $layout_class = 'centershop-fb-feed-' . sanitize_html_class($atts['layout']);
        $columns_class = 'centershop-fb-columns-' . intval($atts['columns']);
        
        ob_start();
        ?>
        <div class="centershop-fb-feed <?php echo esc_attr($layout_class); ?> <?php echo esc_attr($columns_class); ?>">
            <?php foreach ($posts as $post): ?>
                <?php echo $this->render_post($post, $atts); ?>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render single post
     */
    private function render_post($post, $atts) {
        $shop_id = get_post_meta($post->ID, '_centershop_shop_id', true);
        $shop = $shop_id ? get_post($shop_id) : null;
        $image_url = get_post_meta($post->ID, '_centershop_fb_image_url', true);
        $permalink = get_post_meta($post->ID, '_centershop_fb_permalink', true);
        $fb_date = get_post_meta($post->ID, '_centershop_fb_created_time', true);
        $fb_post_id = get_post_meta($post->ID, '_centershop_fb_post_id', true);
        
        // Get engagement data from database
        $db = CenterShop_FB_Database::get_instance();
        $db_post = $fb_post_id ? $db->get_post_by_fb_id($fb_post_id) : null;
        
        // Get excerpt
        $content = $post->post_content;
        $excerpt_length = intval($atts['excerpt_length']);
        if (strlen($content) > $excerpt_length) {
            $content = substr($content, 0, $excerpt_length) . '...';
        }
        
        ob_start();
        ?>
        <article class="centershop-fb-post">
            <?php if ($image_url): ?>
                <div class="centershop-fb-post-image">
                    <?php if ($permalink): ?>
                        <a href="<?php echo esc_url($permalink); ?>" target="_blank" rel="noopener">
                    <?php endif; ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="" loading="lazy">
                    <?php if ($permalink): ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="centershop-fb-post-content">
                <?php if ($atts['show_shop'] === 'yes' && $shop): ?>
                    <div class="centershop-fb-post-shop">
                        <?php echo esc_html($shop->post_title); ?>
                    </div>
                <?php endif; ?>
                
                <div class="centershop-fb-post-text">
                    <?php echo nl2br(esc_html($content)); ?>
                </div>
                
                <?php if ($db_post && ($db_post->likes_count > 0 || $db_post->comments_count > 0 || $db_post->shares_count > 0)): ?>
                    <div class="centershop-fb-engagement">
                        <?php if ($db_post->likes_count > 0): ?>
                            <span class="centershop-fb-likes">👍 <?php echo number_format($db_post->likes_count); ?></span>
                        <?php endif; ?>
                        <?php if ($db_post->comments_count > 0): ?>
                            <span class="centershop-fb-comments">💬 <?php echo number_format($db_post->comments_count); ?></span>
                        <?php endif; ?>
                        <?php if ($db_post->shares_count > 0): ?>
                            <span class="centershop-fb-shares">↗️ <?php echo number_format($db_post->shares_count); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="centershop-fb-post-meta">
                    <?php if ($atts['show_date'] === 'yes' && $fb_date): ?>
                        <span class="centershop-fb-post-date">
                            <?php echo esc_html(date_i18n('j. F Y', strtotime($fb_date))); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($permalink): ?>
                        <a href="<?php echo esc_url($permalink); ?>" class="centershop-fb-post-link" target="_blank" rel="noopener">
                            <?php _e('Se på Facebook', 'centershop_txtdomain'); ?> &rarr;
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }
}

// Initialize
CenterShop_FB_Shortcodes::get_instance();
