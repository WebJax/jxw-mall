<?php
/**
 * Floorplan Block Render
 */

if (!defined('ABSPATH')) {
    exit;
}

$shop_id = isset($attributes['shopId']) ? $attributes['shopId'] : '';
$show_search = isset($attributes['showSearch']) ? $attributes['showSearch'] : true;
$height = isset($attributes['height']) ? $attributes['height'] : '600px';

// Use shortcode to render
echo do_shortcode(sprintf(
    '[centershop_floorplan shop_id="%s" show_search="%s" height="%s"]',
    esc_attr($shop_id),
    $show_search ? 'yes' : 'no',
    esc_attr($height)
));
