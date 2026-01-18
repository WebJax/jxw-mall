<?php
/**
 * Show shopping hours using shortcodes
 * Template for displaying center opening hours
 */

echo '<h2>[shoppingweek]</h2>';
echo do_shortcode( '[shoppingweek]' );
echo '<h2>[collected-shoppingweek]</h2>';
echo do_shortcode( '[collected-shoppingweek]' );
echo '<h2>[shoppingtoday]</h2>';
echo do_shortcode( '[shoppingtoday]' );
echo '<h2>[shopping-extra-text]</h2>';
echo do_shortcode( '[shopping-extra-text]' );
