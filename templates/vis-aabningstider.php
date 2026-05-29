<?php
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
