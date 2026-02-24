<?php
/**
 * Instagram Feed - Shortcodes
 * 
 * Shortcodes til at vise Instagram feed på frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_IG_Shortcodes {
    
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
        add_shortcode('centershop_instagram_feed', array($this, 'render_shortcode'));
        add_shortcode('mall_instagram_feed', array($this, 'render_shortcode')); // Alias
        add_action('wp_enqueue_scripts', array($this, 'register_styles'));
    }
    
    /**
     * Register frontend styles
     */
    public function register_styles() {
        wp_register_style(
            'centershop-ig-feed',
            CENTERSHOP_PLUGIN_URL . 'css/centershop-ig-feed.css',
            array(),
            CENTERSHOP_VERSION
        );
    }
    
    /**
     * Render shortcode
     * 
     * Usage: [centershop_instagram_feed count="10" shop="123" layout="grid"]
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
        ), $atts, 'centershop_instagram_feed');
        
        // Query args
        $args = array(
            'post_type' => CenterShop_IG_Post_Type::POST_TYPE,
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
            return '<p class="centershop-ig-no-posts">' . __('Ingen opslag fundet.', 'centershop_txtdomain') . '</p>';
        }
        
        wp_enqueue_style('centershop-ig-feed');
        
        $layout_class = 'centershop-ig-feed-' . sanitize_html_class($atts['layout']);
        $columns_class = 'centershop-ig-columns-' . intval($atts['columns']);
        
        ob_start();
        ?>
        <div class="centershop-ig-feed <?php echo esc_attr($layout_class); ?> <?php echo esc_attr($columns_class); ?>">
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
        $media_url = get_post_meta($post->ID, '_centershop_ig_media_url', true);
        $permalink = get_post_meta($post->ID, '_centershop_ig_permalink', true);
        $ig_date = get_post_meta($post->ID, '_centershop_ig_timestamp', true);
        $media_type = get_post_meta($post->ID, '_centershop_ig_media_type', true);
        
        // Get excerpt
        $content = $post->post_content;
        $excerpt_length = intval($atts['excerpt_length']);
        if (strlen($content) > $excerpt_length) {
            $content = substr($content, 0, $excerpt_length) . '...';
        }
        
        ob_start();
        ?>
        <article class="centershop-ig-post">
            <?php if ($media_url): ?>
                <div class="centershop-ig-post-image">
                    <?php if ($permalink): ?>
                        <a href="<?php echo esc_url($permalink); ?>" target="_blank" rel="noopener">
                    <?php endif; ?>
                    <img src="<?php echo esc_url($media_url); ?>" alt="" loading="lazy">
                    <?php if ($media_type === 'VIDEO'): ?>
                        <span class="centershop-ig-video-icon">▶</span>
                    <?php endif; ?>
                    <?php if ($permalink): ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="centershop-ig-post-content">
                <?php if ($atts['show_shop'] === 'yes' && $shop): ?>
                    <div class="centershop-ig-post-shop">
                        <?php echo esc_html($shop->post_title); ?>
                    </div>
                <?php endif; ?>
                
                <div class="centershop-ig-post-text">
                    <?php echo nl2br(esc_html($content)); ?>
                </div>
                
                <div class="centershop-ig-post-meta">
                    <?php if ($atts['show_date'] === 'yes' && $ig_date): ?>
                        <span class="centershop-ig-post-date">
                            <?php echo esc_html(date_i18n('j. F Y', strtotime($ig_date))); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($permalink): ?>
                        <a href="<?php echo esc_url($permalink); ?>" class="centershop-ig-post-link" target="_blank" rel="noopener">
                            <?php _e('Se på Instagram', 'centershop_txtdomain'); ?> &rarr;
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
CenterShop_IG_Shortcodes::get_instance();
