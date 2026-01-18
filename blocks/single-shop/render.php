<?php
/**
 * Render callback for the single-shop block
 */

$shop_id = isset($attributes['shopId']) ? intval($attributes['shopId']) : 0;

if (!$shop_id) {
    echo '<p>Ingen butik valgt.</p>';
    return;
}

$shop = get_post($shop_id);

if (!$shop || $shop->post_type !== 'butiksside' || $shop->post_status !== 'publish') {
    echo '<p>Butik ikke fundet.</p>';
    return;
}

// Get meta data
$logo_id = get_post_meta($shop_id, 'allround-cpt_logo_id', true);
$logo_url = $logo_id ? wp_get_attachment_url($logo_id) : '';
$address = get_post_meta($shop_id, 'butik_payed_adress', true);
$postal = get_post_meta($shop_id, 'butik_payed_postal', true);
$city = get_post_meta($shop_id, 'butik_payed_city', true);
$phone = get_post_meta($shop_id, 'butik_payed_phone', true);
$email = get_post_meta($shop_id, 'butik_payed_mail', true);
$web = get_post_meta($shop_id, 'butik_payed_web', true);
$fb = get_post_meta($shop_id, 'butik_payed_fb', true);
$insta = get_post_meta($shop_id, 'butik_payed_insta', true);
$shop_url = get_permalink($shop_id);

?>
<div <?php echo get_block_wrapper_attributes(['class' => 'centershop-single-block']); ?>>
    <div class="centershop-item">
        <div class="centershop-layout">
            <?php if ($logo_url): ?>
                <div class="centershop-logo-left">
                    <a href="<?php echo esc_url($shop_url); ?>" title="<?php echo esc_attr(get_the_title($shop_id)); ?>">
                        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_the_title($shop_id)); ?>">
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="centershop-info-right">
                <h3 class="centershop-title"><?php echo esc_html(get_the_title($shop_id)); ?></h3>
                
                <?php if ($address): ?>
                    <p class="centershop-address">
                        <?php 
                        echo esc_html($address);
                        if ($postal) echo ', ' . esc_html($postal);
                        if ($city) echo ' ' . esc_html($city);
                        ?>
                    </p>
                <?php endif; ?>
                
                <?php if ($phone): ?>
                    <p class="centershop-phone"><?php echo esc_html($phone); ?></p>
                <?php endif; ?>
                
                <?php if ($email || $web || $fb || $insta): ?>
                    <div class="centershop-icons">
                        <?php if ($email): ?>
                            <a href="mailto:<?php echo esc_attr($email); ?>" class="centershop-icon" title="Email">
                                <i class="mail-logo"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($web): ?>
                            <a href="<?php echo esc_url($web); ?>" target="_blank" rel="noopener noreferrer" class="centershop-icon" title="Hjemmeside">
                                <i class="www-logo"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($fb): ?>
                            <a href="<?php echo esc_url($fb); ?>" target="_blank" rel="noopener noreferrer" class="centershop-icon" title="Facebook">
                                <i class="fb-logo"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($insta): ?>
                            <a href="<?php echo esc_url($insta); ?>" target="_blank" rel="noopener noreferrer" class="centershop-icon" title="Instagram">
                                <i class="insta-logo"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
