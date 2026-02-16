<?php
/**
 * Connect Posts (News) with Shops
 * Allows posts to be associated with one or more shops
 * and displays shop pills in the post meta area
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add meta box to posts for selecting associated shops
 */
function jxw_add_shop_connection_meta_box() {
    add_meta_box(
        'jxw_post_shops',
        'Tilknyttede Butikker',
        'jxw_render_shop_connection_meta_box',
        'post',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'jxw_add_shop_connection_meta_box');

/**
 * Render the meta box content
 */
function jxw_render_shop_connection_meta_box($post) {
    // Add nonce for security
    wp_nonce_field('jxw_shop_connection_nonce', 'jxw_shop_connection_nonce');
    
    // Get current selected shops
    $selected_shops = get_post_meta($post->ID, '_jxw_connected_shops', true);
    if (!is_array($selected_shops)) {
        $selected_shops = array();
    }
    
    // Get all shops
    $shops = get_posts(array(
        'post_type' => 'butiksside',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'post_status' => 'publish'
    ));
    
    if (empty($shops)) {
        echo '<p>Ingen butikker fundet.</p>';
        return;
    }
    
    echo '<p>Vælg en eller flere butikker der er relateret til denne nyhed:</p>';
    echo '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">';
    
    foreach ($shops as $shop) {
        $checked = in_array($shop->ID, $selected_shops) ? 'checked' : '';
        echo '<label style="display: block; margin-bottom: 8px;">';
        echo '<input type="checkbox" name="jxw_connected_shops[]" value="' . esc_attr($shop->ID) . '" ' . $checked . '> ';
        echo esc_html($shop->post_title);
        echo '</label>';
    }
    
    echo '</div>';
}

/**
 * Save the shop connections when post is saved
 */
function jxw_save_shop_connections($post_id) {
    // Security checks
    if (!isset($_POST['jxw_shop_connection_nonce'])) {
        return;
    }
    
    if (!wp_verify_nonce($_POST['jxw_shop_connection_nonce'], 'jxw_shop_connection_nonce')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Save selected shops
    if (isset($_POST['jxw_connected_shops']) && is_array($_POST['jxw_connected_shops'])) {
        $shop_ids = array_map('intval', $_POST['jxw_connected_shops']);
        update_post_meta($post_id, '_jxw_connected_shops', $shop_ids);
    } else {
        delete_post_meta($post_id, '_jxw_connected_shops');
    }
}
add_action('save_post_post', 'jxw_save_shop_connections');

/**
 * Display connected shops as pills in post content or meta
 */
function jxw_display_connected_shops($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $connected_shops = get_post_meta($post_id, '_jxw_connected_shops', true);
    
    if (empty($connected_shops) || !is_array($connected_shops)) {
        return '';
    }
    
    $output = '<div class="jxw-connected-shops">';
    
    foreach ($connected_shops as $shop_id) {
        $shop = get_post($shop_id);
        if ($shop && $shop->post_status === 'publish') {
            $logo_url = wp_get_attachment_url(get_post_meta($shop_id, 'allround-cpt_logo_id', true)); 
            $shop_url = get_permalink($shop_id);
            $output .= '<a href="' . esc_url($shop_url) . '" class="jxw-shop-pill">';
            if ($logo_url) {
                $output .= '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($shop->post_title) . '">';
            }
            $output .= esc_html($shop->post_title);
            $output .= '</a>';
        }
    }
    
    $output .= '</div>';
    
    return $output;
}

/**
 * Automatically add connected shops to post content
 * (hooked to the_content filter)
 */
function jxw_add_shops_to_content($content) {
    // Only on single post pages
    if (!is_singular('post')) {
        return $content;
    }
    
    $shops = jxw_display_connected_shops();
    
    if (!empty($shops)) {
        // Add shops before the content
        $content = $shops . $content;
    }
    
    return $content;
}
add_filter('the_content', 'jxw_add_shops_to_content', 10);

/**
 * Shortcode to display connected shops
 * Usage: [connected_shops]
 */
function jxw_connected_shops_shortcode($atts) {
    $atts = shortcode_atts(array(
        'post_id' => get_the_ID()
    ), $atts);
    
    return jxw_display_connected_shops($atts['post_id']);
}
add_shortcode('connected_shops', 'jxw_connected_shops_shortcode');

/**
 * Enqueue styles for the shop pills
 */
function jxw_enqueue_shop_pills_styles() {
    if (is_singular('post')) {
        wp_enqueue_style(
            'jxw-shop-pills',
            CENTERSHOP_PLUGIN_URL . 'css/shop-pills.css',
            array(),
            filemtime(plugin_dir_path(__FILE__) . '../css/shop-pills.css')
        );
    }
}
add_action('wp_enqueue_scripts', 'jxw_enqueue_shop_pills_styles');

/**
 * Get posts connected to a specific shop
 * Useful for displaying on shop pages
 */
function jxw_get_posts_for_shop($shop_id) {
    global $wpdb;
    
    $query = $wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} 
        WHERE meta_key = '_jxw_connected_shops' 
        AND meta_value LIKE %s",
        '%"' . $shop_id . '"%'
    );
    
    $post_ids = $wpdb->get_col($query);
    
    if (empty($post_ids)) {
        return array();
    }
    
    return get_posts(array(
        'post_type' => 'post',
        'post__in' => $post_ids,
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    ));
}

/**
 * Display related posts on shop single page
 */
function jxw_display_shop_related_posts($shop_id = null) {
    if (!$shop_id) {
        $shop_id = get_the_ID();
    }
    
    $posts = jxw_get_posts_for_shop($shop_id);
    
    if (empty($posts)) {
        return '';
    }
    
    $output = '<div class="jxw-shop-related-posts">';
    $output .= '<h3>Nyheder fra ' . get_the_title($shop_id) . '</h3>';
    $output .= '<ul>';
    
    foreach ($posts as $post) {
        $output .= '<li>';
        $output .= '<a href="' . get_permalink($post->ID) . '">' . esc_html($post->post_title) . '</a>';
        $output .= ' - ' . get_the_date('d. F Y', $post->ID);
        $output .= '</li>';
    }
    
    $output .= '</ul>';
    $output .= '</div>';
    
    return $output;
}

/**
 * Shortcode to display related posts on shop page
 * Usage: [shop_related_posts]
 */
function jxw_shop_related_posts_shortcode($atts) {
    $atts = shortcode_atts(array(
        'shop_id' => get_the_ID()
    ), $atts);
    
    return jxw_display_shop_related_posts($atts['shop_id']);
}
add_shortcode('shop_related_posts', 'jxw_shop_related_posts_shortcode');
