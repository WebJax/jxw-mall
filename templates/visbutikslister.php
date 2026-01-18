<?php
/*
Template Name: Butiksliste
*/

get_header();

echo '<div class="container">';

the_title( '<h1 class="overskrift">','</h1>' );

echo "<div class='sideindhold'>";
the_content();
echo "</div>";

include plugin_dir_path( __FILE__ ) . 'content-visbutiksliste.php';

// Only include content-links if it exists in theme
if ( locate_template( 'template-parts/content-links.php' ) ) {
    get_template_part('template-parts/content', 'links');
}

echo "</div>";

get_footer();

?>