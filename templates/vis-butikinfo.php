<div class="butikinfo">
  <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
    <h4 class="butikerhvervnavn butik-<?php echo $post->ID;?>"><?php echo $post->butik_payed_name ?></h4>
  </a>
  <?php echo $post->butik_payed_adress;?><br/>
  <?php echo $post->butik_payed_postal." ".$post->butik_payed_city; ?><br/>
  <?php echo "Telefon: ".$post->butik_payed_phone; ?><br/>
  <div style="margin: 2rem auto -1rem;">
  <?php 
  $logo = false; 
  if ($post->butik_payed_mail != '') { 
    $logo = true; ?>
    <a class="cpt-mailadress" href="mailto:<?php echo $post->butik_payed_mail; ?>"><i class="mail-logo"></i></a> <?php }
  if ($post->butik_payed_web != '') { 
    $logo = true; ?>
    <a href="<?php echo $post->butik_payed_web ?>" target="_blank"><i class="www-logo"></i></a> <?php }
  if ($post->butik_payed_fb != '') { 
    $logo = true; ?>
    <a href="<?php echo $post->butik_payed_fb; ?>" target="_blank"><i class="fb-logo"></i></a>	<?php }
  if ($post->butik_payed_insta != '') { 
    $logo = true; ?>
    <a href="<?php echo $post->butik_payed_insta; ?>" target="_blank"><i class="insta-logo"></i></a><?php	}
  if (!$logo) { ?>
    <div class="no-sociallogo-for-shop"></div>
  <?php } ?>
  </div>
</div>