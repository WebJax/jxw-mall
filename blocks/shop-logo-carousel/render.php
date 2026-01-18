<?php
/**
 * Render callback for shop logo ticker block
 */

$logo_height = isset($attributes['logoHeight']) ? $attributes['logoHeight'] : 80;
$animation_speed = isset($attributes['animationSpeed']) ? $attributes['animationSpeed'] : 30;

// Get all shops
$shops = get_posts(array(
    'post_type' => 'butiksside',
    'numberposts' => -1,
    'post_status' => 'publish',
    'orderby' => 'title',
    'order' => 'ASC'
));

if (empty($shops)) {
    echo '<p>Ingen butikker fundet.</p>';
    return;
}

// Generate unique ID for this ticker instance
$ticker_id = 'ticker-' . uniqid();

// Calculate speed in ms (convert seconds to milliseconds per slide)
$speed_per_slide = ($animation_speed * 1000) / count($shops);
?>

<!-- Swiper CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

<div class="centershop-logo-ticker-wrapper" 
     id="<?php echo esc_attr($ticker_id); ?>"
     style="--logo-height: <?php echo esc_attr($logo_height); ?>px;">
    
    <div class="swiper">
        <div class="swiper-wrapper">
            <?php 
            foreach ($shops as $shop): 
                $logo_id = get_post_meta($shop->ID, 'allround-cpt_logo_id', true);
                $shop_url = get_permalink($shop->ID);
                
                if ($logo_id):
                    $logo_url = wp_get_attachment_image_url($logo_id, 'medium');
                    if ($logo_url):
            ?>
                <div class="swiper-slide">
                    <div class="ticker-logo-item">
                        <a href="<?php echo esc_url($shop_url); ?>" 
                           title="<?php echo esc_attr($shop->post_title); ?>">
                            <img src="<?php echo esc_url($logo_url); ?>" 
                                 alt="<?php echo esc_attr($shop->post_title); ?>" />
                        </a>
                    </div>
                </div>
            <?php 
                    endif;
                endif;
            endforeach;
            ?>
        </div>
    </div>
</div>

<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<script>
(function() {
    var ticker = document.getElementById('<?php echo $ticker_id; ?>');
    if (!ticker) return;
    
    var swiperEl = ticker.querySelector('.swiper');
    if (!swiperEl) return;
    
    // Wait for Swiper to be loaded
    var initSwiper = function() {
        if (typeof Swiper === 'undefined') {
            setTimeout(initSwiper, 100);
            return;
        }
        
        new Swiper(swiperEl, {
            slidesPerView: 'auto',
            spaceBetween: 40,
            speed: 600,
            freeMode: {
                enabled: true,
                momentum: true,
                momentumRatio: 0.5,
                momentumVelocityRatio: 0.5
            },
            loop: true,
            loopAdditionalSlides: 3,
            autoplay: {
                delay: 0,
                disableOnInteraction: false,
                pauseOnMouseEnter: true
            },
            speed: <?php echo max(1000, (int)$speed_per_slide); ?>,
            grabCursor: true,
            breakpoints: {
                320: {
                    spaceBetween: 20
                },
                768: {
                    spaceBetween: 30
                },
                1024: {
                    spaceBetween: 40
                }
            }
        });
    };
    
    initSwiper();
})();
</script>
