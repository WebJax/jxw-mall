<?php
/**
 * EKSEMPEL: Integration af butik-pills i DianalundCentret tema
 * 
 * Denne fil viser hvordan du kan integrere butik-pills i dit tema.
 * Kopier den relevante kode til dit tema's functions.php eller template filer.
 */

// ============================================
// METODE 1: Tilføj til Post Meta Area
// ============================================
// Tilføj denne kode til template-parts/content-single.php efter udgivelses-datoen

/*
<p class="udgivet-den">Udgivet den <?php the_date(); ?></p>
<?php 
// Vis forbundne butikker som pills
if (function_exists('jxw_display_connected_shops')) {
    echo jxw_display_connected_shops();
}
?>
*/


// ============================================
// METODE 2: Inline Display (Pills ved siden af meta)
// ============================================
// Erstat den eksisterende "udgivet-den" linje med denne:

/*
<div class="post-meta-wrapper">
    <p class="udgivet-den">Udgivet den <?php the_date(); ?></p>
    <?php 
    if (function_exists('jxw_display_connected_shops')) {
        $shops = jxw_display_connected_shops();
        if ($shops) {
            echo str_replace('class="jxw-connected-shops"', 'class="jxw-connected-shops inline"', $shops);
        }
    }
    ?>
</div>
*/


// ============================================
// METODE 3: Custom Styling i Tema
// ============================================
// Tilføj til dit tema's style.css for at override plugin styling

/*
/* Tilpas butik pills til dit tema *
.jxw-connected-shops {
    margin: 15px 0;
}

.jxw-shop-pill {
    background: linear-gradient(135deg, #your-color-1 0%, #your-color-2 100%);
    font-family: 'YourFont', sans-serif;
}

/* Match dit tema's farver *
.jxw-shop-pill:hover {
    background: linear-gradient(135deg, #your-hover-color-1 0%, #your-hover-color-2 100%);
}
*/


// ============================================
// METODE 4: Widget til Sidebar (Advanced)
// ============================================
// Tilføj til dit tema's functions.php

function dianalund_shop_connection_widget() {
    if (!is_singular('post')) {
        return;
    }
    
    $connected_shops = get_post_meta(get_the_ID(), '_jxw_connected_shops', true);
    
    if (empty($connected_shops)) {
        return;
    }
    
    echo '<div class="widget widget-shop-connections">';
    echo '<h3 class="widget-title">Relaterede Butikker</h3>';
    echo '<div class="widget-content">';
    
    foreach ($connected_shops as $shop_id) {
        $shop = get_post($shop_id);
        if ($shop && $shop->post_status === 'publish') {
            echo '<div class="shop-item">';
            
            // Vis butik logo
            if (has_post_thumbnail($shop_id)) {
                echo '<a href="' . get_permalink($shop_id) . '">';
                echo get_the_post_thumbnail($shop_id, 'thumbnail', array('class' => 'shop-logo'));
                echo '</a>';
            }
            
            // Vis butik info
            echo '<div class="shop-info">';
            echo '<h4><a href="' . get_permalink($shop_id) . '">' . esc_html($shop->post_title) . '</a></h4>';
            
            // Vis butik beskrivelse (første 100 tegn)
            $excerpt = wp_trim_words(get_the_excerpt($shop_id), 15);
            echo '<p>' . $excerpt . '</p>';
            
            echo '<a href="' . get_permalink($shop_id) . '" class="read-more">Læs mere →</a>';
            echo '</div>';
            
            echo '</div>'; // .shop-item
        }
    }
    
    echo '</div>'; // .widget-content
    echo '</div>'; // .widget
}

// Tilføj widget til sidebar på single posts
// add_action('dynamic_sidebar_before', 'dianalund_shop_connection_widget');


// ============================================
// METODE 5: Vis på Butikssiden (Related Posts)
// ============================================
// Tilføj til templates/single-butiksside.php

/*
<div class="butik-related-news">
    <?php
    if (function_exists('jxw_display_shop_related_posts')) {
        echo jxw_display_shop_related_posts();
    }
    ?>
</div>
*/


// ============================================
// METODE 6: Archive Filter (Filtrer efter butik)
// ============================================
// Tilføj til dit tema's functions.php

function dianalund_add_shop_filter_to_archive() {
    if (!is_home() && !is_archive()) {
        return;
    }
    
    $shops = get_posts(array(
        'post_type' => 'butiksside',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ));
    
    if (empty($shops)) {
        return;
    }
    
    $current_shop = isset($_GET['shop_filter']) ? intval($_GET['shop_filter']) : 0;
    
    echo '<div class="shop-filter-wrapper">';
    echo '<form method="get" class="shop-filter-form">';
    echo '<label for="shop_filter">Filtrer efter butik:</label>';
    echo '<select name="shop_filter" id="shop_filter" onchange="this.form.submit()">';
    echo '<option value="">Alle nyheder</option>';
    
    foreach ($shops as $shop) {
        $selected = ($current_shop == $shop->ID) ? 'selected' : '';
        echo '<option value="' . $shop->ID . '" ' . $selected . '>' . esc_html($shop->post_title) . '</option>';
    }
    
    echo '</select>';
    echo '</form>';
    echo '</div>';
}
// add_action('archive_title_after', 'dianalund_add_shop_filter_to_archive');

// Modificer query for at filtrere
function dianalund_filter_posts_by_shop($query) {
    if (!is_admin() && $query->is_main_query() && (is_home() || is_archive())) {
        if (isset($_GET['shop_filter']) && !empty($_GET['shop_filter']))) {
            $shop_id = intval($_GET['shop_filter']);
            
            $meta_query = array(
                array(
                    'key' => '_jxw_connected_shops',
                    'value' => '"' . $shop_id . '"',
                    'compare' => 'LIKE'
                )
            );
            
            $query->set('meta_query', $meta_query);
        }
    }
}
// add_filter('pre_get_posts', 'dianalund_filter_posts_by_shop');


// ============================================
// METODE 7: Custom Post Grid med Butik Info
// ============================================
// Tilføj til en custom template eller page builder

function dianalund_posts_grid_with_shops() {
    $args = array(
        'post_type' => 'post',
        'posts_per_page' => 6,
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    $posts = new WP_Query($args);
    
    if (!$posts->have_posts()) {
        return;
    }
    
    echo '<div class="posts-grid">';
    
    while ($posts->have_posts()) {
        $posts->the_post();
        
        echo '<article class="post-grid-item">';
        
        // Featured image
        if (has_post_thumbnail()) {
            echo '<a href="' . get_permalink() . '" class="post-thumbnail">';
            the_post_thumbnail('medium');
            echo '</a>';
        }
        
        echo '<div class="post-content">';
        
        // Title
        echo '<h3><a href="' . get_permalink() . '">' . get_the_title() . '</a></h3>';
        
        // Date
        echo '<p class="post-date">' . get_the_date() . '</p>';
        
        // Connected shops
        $connected_shops = get_post_meta(get_the_ID(), '_jxw_connected_shops', true);
        if (!empty($connected_shops)) {
            echo '<div class="post-shops-mini">';
            foreach (array_slice($connected_shops, 0, 2) as $shop_id) {
                $shop = get_post($shop_id);
                if ($shop) {
                    echo '<span class="shop-tag">' . esc_html($shop->post_title) . '</span>';
                }
            }
            if (count($connected_shops) > 2) {
                echo '<span class="shop-tag">+' . (count($connected_shops) - 2) . '</span>';
            }
            echo '</div>';
        }
        
        // Excerpt
        echo '<p>' . get_the_excerpt() . '</p>';
        
        echo '<a href="' . get_permalink() . '" class="read-more-btn">Læs mere</a>';
        
        echo '</div>'; // .post-content
        
        echo '</article>';
    }
    
    echo '</div>'; // .posts-grid
    
    wp_reset_postdata();
}

// Brug shortcode: [posts_grid_with_shops]
// add_shortcode('posts_grid_with_shops', 'dianalund_posts_grid_with_shops');


// ============================================
// CSS til ovenstående eksempler
// ============================================
/*
/* Widget Styling *
.widget-shop-connections .shop-item {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.widget-shop-connections .shop-logo {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 8px;
}

.widget-shop-connections .shop-info h4 {
    margin: 0 0 8px 0;
    font-size: 16px;
}

.widget-shop-connections .shop-info p {
    margin: 0 0 8px 0;
    font-size: 14px;
    color: #666;
}

/* Archive Filter *
.shop-filter-wrapper {
    margin: 20px 0;
    padding: 15px;
    background: #f5f5f5;
    border-radius: 8px;
}

.shop-filter-form select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

/* Posts Grid *
.posts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 30px;
    margin: 30px 0;
}

.post-grid-item {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.post-grid-item:hover {
    transform: translateY(-5px);
}

.post-grid-item .post-thumbnail {
    display: block;
    overflow: hidden;
}

.post-grid-item .post-thumbnail img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.post-grid-item:hover .post-thumbnail img {
    transform: scale(1.05);
}

.post-grid-item .post-content {
    padding: 20px;
}

.post-shops-mini {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    margin: 10px 0;
}

.post-shops-mini .shop-tag {
    padding: 3px 8px;
    background: #e0e0e0;
    border-radius: 3px;
    font-size: 11px;
    color: #666;
}
*/
