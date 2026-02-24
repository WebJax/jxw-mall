<?php
/**
 * Instagram Feed Block - Render
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get attributes
$count = isset($attributes['count']) ? intval($attributes['count']) : 10;
$shop_id = isset($attributes['shopId']) ? intval($attributes['shopId']) : 0;
$layout = isset($attributes['layout']) ? sanitize_text_field($attributes['layout']) : 'grid';
$columns = isset($attributes['columns']) ? intval($attributes['columns']) : 3;
$show_date = isset($attributes['showDate']) ? $attributes['showDate'] : true;
$show_shop = isset($attributes['showShop']) ? $attributes['showShop'] : true;
$excerpt_length = isset($attributes['excerptLength']) ? intval($attributes['excerptLength']) : 150;

// Build shortcode attributes
$shortcode_atts = array(
    'count="' . $count . '"',
    'layout="' . $layout . '"',
    'columns="' . $columns . '"',
    'show_date="' . ($show_date ? 'yes' : 'no') . '"',
    'show_shop="' . ($show_shop ? 'yes' : 'no') . '"',
    'excerpt_length="' . $excerpt_length . '"'
);

if ($shop_id > 0) {
    $shortcode_atts[] = 'shop="' . $shop_id . '"';
}

// Render using shortcode
echo do_shortcode('[centershop_instagram_feed ' . implode(' ', $shortcode_atts) . ']');
