  <section class="container sideindhold posts">
    <?php
    if ('' == get_the_content()) { ?>
      <div class="row">
        <div class="offset-by-four four columns">
          <div class="breadcrumb"><?php get_breadcrumb(); ?></div>
		  <?php edit_post_link('<button class="edit-logged-in">Ret siden</button>','',''); ?>
          <?php the_title('<h1 class="butiksnavn">','</h1>'); ?>
        </div>
      </div>
      <div class="row">
        <div class="offset-by-four four columns">
          <?php
          include plugin_dir_path( __FILE__ ) . 'vis-butikinfo.php';
          include plugin_dir_path( __FILE__ ) . 'vis-aabningstider.php';
          ?>
        </div>
      </div>
    <?php } else { ?>
    <div class="row">
      <div class="three columns ">
        <p></p>
      </div>
      <div class="six columns">
        <div class="breadcrumb"><?php get_breadcrumb(); ?></div>
		  <?php edit_post_link('<button class="edit-logged-in">Ret siden</button>','',''); ?>
          <?php the_title('<h1 class="butiksnavn">','</h1>'); ?>
      </div>
      <div class="three columns">
        <p></p>
      </div>    
    </div>                
    <div class="row">
      <div class="three columns">
        <?php
          $image_id = get_post_meta( $post->ID, 'allround-cpt_logo_id', true );
          if (!empty($image_id)) {
            echo '<img id="allround-cpt-logo" src="'.wp_get_attachment_url( $image_id ).'" style="max-width:100%;" />';
          } 
          include plugin_dir_path( __FILE__ ) . 'vis-butikinfo.php';
        ?>
      </div>
      <div class="six columns indholdet">
        <?php the_content(); ?>
      </div>
      <div class="three columns">
      <?php
        include plugin_dir_path( __FILE__ ) . 'vis-aabningstider.php';
      ?>
      </div>
    </div>
    <?php } ?>
  </section>
  <section class="butik-related-news">
    <?php
    if (function_exists('jxw_display_shop_related_posts')) {
        echo jxw_display_shop_related_posts();
    }
    ?>
  </section>