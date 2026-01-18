<?php
// Shortcode to show branches in shoppingcenter
function dc_show_branches () {
  $catIds = array();
  
  $catIds[] = 13;  // Specialbutikker
  $catIds[] = 7;   // Fødevarebutikker
  $catIds[] = 23;  // Pengeinstitut
  $catIds[] = 14;  // Spisesteder
  $catIds[] = 13;  // Fitnesscenter
  
  $cat_array = array();
  
  foreach ($catIds as $catId) {
    $cat = get_category($catId);
    $cat_array[$catId]['antal'] = $cat->count;
    $cat_array[$catId]['navn'] = $cat->name; 
  }
  
  $last = end($cat_array);
  foreach ($cat_array as $info) {
    $output .= $info['antal'] . ' ' . $info['navn'] . ($last == $info) ? '' : ' o ';
  }
  
  return $output;
}

add_shortcode( 'dc_show_all', 'dc_show_branches' );


// Setting up a shortcode for selecting branches from the tinyMCE editor custom button

/* Add branch shortcode */
function diana_brancheliste( $diana_branche_attr ) {
	$diana_branche_attr = shortcode_atts (array (
		'diana_branche' => '0',
	), $diana_branche_attr, 'diana-erhvervsliste');
  
  $db = intval($diana_branche_attr['diana_branche']);
  $branche_thumbnail = ""; //get_the_post_thumbnail(get_the_ID(), 'medium');
  
	$args=array(
		'post_type' => array('butiksside'),
		'posts_per_page' 	=> -1,
		'orderby' 				=> 'title',
		'order'   				=> 'ASC',
   	'tax_query' => array(
	  	array(
		  	'taxonomy' => 'category',
			  'field'    => 'term_id',
			  'terms'    => $db,
		  ),
	  ), 	
  );
	$my_query = null;
	$my_query = new WP_Query($args);
	ob_start();
//	echo "<p>Hvad indeholder attributten: ".$db."</p>";
	if( $my_query->have_posts() ) {
    echo '<div class="erhvervsliste-slider">';
		while ($my_query->have_posts()) : $my_query->the_post(); 
      $cat_array = get_the_category(); ?>
			<a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link til <?php the_title_attribute(); ?>">
				<div class="erhvervsliste">
					<div class="erhvervsliste-thumbnail-sliderimage">
            <?php 
            $butikerhverv_thumbnail = get_the_post_thumbnail(get_the_ID(), 'medium');
						$payed = get_post_meta(get_the_ID(), 'butik_payed_payedbutik', true);
						if ($payed && $payed != 'Intet') { 
              echo $butikerhverv_thumbnail;
            } else {
              echo $branche_thumbnail;
            }
              
            ?>
          </div>
          <div class="erhvervsliste-kontakt">
						<h5><?php the_title(); ?></h5>
		        <?php 
            echo '<p class="erhvervsliste-adresse">'.get_post_meta (get_the_ID(), 'butik_payed_adress', true).'</p>';
            echo '<p class="erhvervsliste-postnrby">'.get_post_meta (get_the_ID(), 'butik_payed_postal', true)." ".get_post_meta (get_the_ID(), 'butik_payed_city', true).'</p>'; ?>
          </div>
				</div>
			</a> <?php
		endwhile;
    echo "</div>";
  }
	wp_reset_query();
	return ob_get_clean();
}

add_shortcode( 'diana-erhvervsliste', 'diana_brancheliste' );

// // add new tinymce-buttons
// add_filter( 'mce_buttons', 'dianalund_cpt_register_buttons' );

// function dianalund_cpt_register_buttons( $buttons ) {
//    array_push( $buttons, 'separator', 'dianalund_cpt' );
//    return $buttons;
// }

// wp_enqueue_script( 'dianalund-cpt-tinymce-plugin', plugins_url( '/js/tinymce-plugin.js',dirname(__FILE__) ) );

// $dianalund_cpt_categories = get_categories();
                  
// wp_localize_script( 'dianalund-cpt-tinymce-plugin', 'php_vars', $dianalund_cpt_categories );


// // Load the TinyMCE plugin : editor_plugin.js (wp2.5)
// add_filter( 'mce_external_plugins', 'dianalund_cpt_register_tinymce_javascript' );

// function dianalund_cpt_register_tinymce_javascript( $plugin_array ) {
//    $plugin_array['dianalund_cpt'] = plugins_url( '/js/tinymce-plugin.js',dirname(__FILE__) );
//    return $plugin_array;
// }