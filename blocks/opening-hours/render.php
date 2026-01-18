<?php
/**
 * Render callback for opening hours block
 */

$show_week = isset($attributes['showWeek']) ? $attributes['showWeek'] : false;
$show_collected = isset($attributes['showCollected']) ? $attributes['showCollected'] : true;
$show_today = isset($attributes['showToday']) ? $attributes['showToday'] : false;
$show_extra_text = isset($attributes['showExtraText']) ? $attributes['showExtraText'] : false;

// If no format is selected, show collected by default
if (!$show_week && !$show_collected && !$show_today && !$show_extra_text) {
    $show_collected = true;
}

?>
<div class="centershop-opening-hours-block">
    <?php 
    if ($show_today) {
        echo do_shortcode('[shoppingtoday]');
    }
    
    if ($show_week) {
        echo do_shortcode('[shoppingweek]');
    }
    
    if ($show_collected) {
        echo do_shortcode('[collected-shoppingweek]');
    }
    
    if ($show_extra_text) {
        echo do_shortcode('[shopping-extra-text]');
    }
    ?>
</div>
