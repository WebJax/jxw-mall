<?php
$aabentider = get_post_meta ($post->ID, 'butik_aabentider', true);
if ($aabentider <> '') {?>
<div class="aabningstider-box">
  <h5>Åbningstider</h5>
<?php
echo $aabentider; }
?>
</div>