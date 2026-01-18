<?php
/* Get categories */
$butikkategorier = get_terms( array (
  'taxonomy'  => 'category',
  'fields'    => 'all',   
  'child_of'  => 27, //Butikker kategorien
) );

echo '<h4 class="kategori-overskrift">Kategorier</h4>';
echo '<ul class="butikskategorier">';
foreach ($butikkategorier as $butikkategori) {
  echo '<li class="butikskategori-overskrift" data-cat="'.$butikkategori->term_id.'">'.$butikkategori->name.'</li>';
}
echo '</ul>';



// echo '<div class="scroll-container">
//         <div class="kategorier-arrow-left"><i class="ion-ios-arrow-back"></i></div>
//           <ul class="butiks-kategorier">';
// foreach ($butikkategorier as $butikkategori) {
//   echo '<li class="butikskategori-overskrift" data-cat="'.$butikkategori->term_id.'">'.$butikkategori->name.'</li>';
// }
// echo '  </ul>
//       <div class="kategorier-arrow-right"><i class="ion-ios-arrow-forward"></i></div>
//     </div>';

$butikker = new WP_Query ( array (
  'post_type'   => array('butiksside'),
  'post_status' => 'publish',
  'orderby' => 'title',
  'order'   => 'DESC',
  'posts_per_page' => -1,
) );

echo '<ul class="butiksliste">';
while ( $butikker->have_posts() ) {
  global $post;
  $butikker->the_post();

  global $catId;
  $catId = get_cat_id_by_post_id( $post );
  
  include plugin_dir_path( __FILE__ ) . 'list-butik.php';
}
echo '</ul>';

/*
 * Get category ID from parentt category of 'Butkker' / cateID = 27
 *
 * Return catid
 *
 * TODO: Skal returnere alle categorier under categpri 27 og ikke kun den først fundne, et Array i stedetfor
 */

function get_cat_id_by_post_id( $post ) {
  $catid = false;
  $cat = get_the_category($post->ID);
  foreach ($cat as $category) {
    if ($category->category_parent == 27) {
      $catid = $category->cat_ID;
    }
  }

  return $catid;
}