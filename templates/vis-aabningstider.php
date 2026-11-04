<?php

/**
 * Template for displaying the opening hours of a shop.
 *
 * @package CenterShop
 * @subpackage Templates
 * @since 1.8.1
 */

global $post;

// Check the shops status
$shop_status = get_post_meta( $post->ID, 'butik_availability', true );
if ( $shop_status === 'coming_soon' ) {
    echo '<div class="aabningstider-box"><h5>Åbningstider</h5>Kommer snart</div>';
    return;
}
if ( $shop_status === 'for_rent' ) {
    echo '<div class="aabningstider-box"><h5>Åbningstider</h5>Til leje / salg</div>';
    return;
}

// Try structured v2 data first; fall back to legacy HTML if not yet migrated.
$v2_html = function_exists( 'jxw_render_butik_hours_html' ) ? jxw_render_butik_hours_html( $post->ID ) : '';
if ( $v2_html !== '' ) {
    echo '<div class="aabningstider-box"><h5>Åbningstider</h5>' . wp_kses_post( $v2_html ) . '</div>';
} else {
    $aabentider = get_post_meta( $post->ID, 'butik_aabentider', true );
    if ( $aabentider !== '' ) {
        echo '<div class="aabningstider-box"><h5>Åbningstider</h5>' . wp_kses_post( $aabentider ) . '</div>';
    }
}
