<?php
get_header();

if ( have_posts() ) : while ( have_posts() ) : the_post();
    include plugin_dir_path( __FILE__ ) . 'content-visbutik.php';
endwhile;
else :
    _e( 'Sorry, no posts matched your criteria.', 'centershop_txtdomain' );
endif;

get_footer();
