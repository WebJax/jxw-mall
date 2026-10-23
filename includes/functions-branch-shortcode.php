<?php
/**
 * 
 * Show branches in shoppingcenter
 * 
 * @return string The branch list
 */ 
function dc_show_branches () {
  $catIds = array();
  $output = "";
  
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