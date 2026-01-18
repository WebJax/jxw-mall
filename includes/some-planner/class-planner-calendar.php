<?php
/**
 * SoMe Planner - Calendar View
 * 
 * Kalendervisning af planlagte opslag
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_Planner_Calendar {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('centershop_render_planner', array($this, 'render_planner'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_planner_assets'));
    }
    
    /**
     * Enqueue planner assets
     */
    public function enqueue_planner_assets($hook) {
        if (strpos($hook, 'centershop-planner') === false) {
            return;
        }
        
        wp_enqueue_style(
            'centershop-planner',
            plugins_url('/css/centershop-planner.css', dirname(dirname(__FILE__))),
            array(),
            filemtime(plugin_dir_path(dirname(dirname(__FILE__))) . 'css/centershop-planner.css')
        );
        
        wp_enqueue_script(
            'centershop-planner',
            plugins_url('/js/centershop-planner.js', dirname(dirname(__FILE__))),
            array('jquery', 'wp-api-fetch'),
            filemtime(plugin_dir_path(dirname(dirname(__FILE__))) . 'js/centershop-planner.js'),
            true
        );
        
        // Get shops for filter
        $shops = get_posts(array(
            'post_type' => 'butiksside',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        $shops_data = array();
        foreach ($shops as $shop) {
            $shops_data[] = array(
                'id' => $shop->ID,
                'name' => $shop->post_title
            );
        }
        
        wp_localize_script('centershop-planner', 'centershopPlanner', array(
            'apiUrl' => rest_url('centershop/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url(),
            'shops' => $shops_data,
            'isShopManager' => CenterShop_Shop_Roles::is_shop_manager(),
            'userShopId' => CenterShop_Shop_Roles::get_user_shop_id(),
            'strings' => array(
                'draft' => __('Kladde', 'centershop_txtdomain'),
                'awaiting_content' => __('Afventer indhold', 'centershop_txtdomain'),
                'ready' => __('Klar', 'centershop_txtdomain'),
                'published' => __('Publiceret', 'centershop_txtdomain'),
                'post' => __('Opslag', 'centershop_txtdomain'),
                'reel' => __('Reel', 'centershop_txtdomain'),
                'story' => __('Story', 'centershop_txtdomain'),
                'no_shop' => __('Center generelt', 'centershop_txtdomain'),
                'confirm_delete' => __('Er du sikker på at du vil slette dette opslag?', 'centershop_txtdomain'),
                'months' => array(
                    __('Januar', 'centershop_txtdomain'),
                    __('Februar', 'centershop_txtdomain'),
                    __('Marts', 'centershop_txtdomain'),
                    __('April', 'centershop_txtdomain'),
                    __('Maj', 'centershop_txtdomain'),
                    __('Juni', 'centershop_txtdomain'),
                    __('Juli', 'centershop_txtdomain'),
                    __('August', 'centershop_txtdomain'),
                    __('September', 'centershop_txtdomain'),
                    __('Oktober', 'centershop_txtdomain'),
                    __('November', 'centershop_txtdomain'),
                    __('December', 'centershop_txtdomain'),
                ),
                'weekdays' => array(
                    __('Man', 'centershop_txtdomain'),
                    __('Tir', 'centershop_txtdomain'),
                    __('Ons', 'centershop_txtdomain'),
                    __('Tor', 'centershop_txtdomain'),
                    __('Fre', 'centershop_txtdomain'),
                    __('Lør', 'centershop_txtdomain'),
                    __('Søn', 'centershop_txtdomain'),
                )
            )
        ));
        
        // Media uploader
        wp_enqueue_media();
    }
    
    /**
     * Render planner page
     */
    public function render_planner() {
        $is_shop_manager = CenterShop_Shop_Roles::is_shop_manager();
        $user_shop_id = CenterShop_Shop_Roles::get_user_shop_id();
        
        ?>
        <div class="wrap centershop-planner-wrap">
            <h1>
                <?php _e('SoMe Planner', 'centershop_txtdomain'); ?>
                <?php if (!$is_shop_manager): ?>
                    <a href="<?php echo admin_url('post-new.php?post_type=centershop_post'); ?>" class="page-title-action">
                        <?php _e('Tilføj nyt opslag', 'centershop_txtdomain'); ?>
                    </a>
                <?php endif; ?>
            </h1>
            
            <?php if ($is_shop_manager): ?>
                <div class="centershop-shop-notice">
                    <p>
                        <?php 
                        $shop = get_post($user_shop_id);
                        printf(
                            __('Du ser opslag for: <strong>%s</strong>. Upload billeder og video herunder, så centret kan bruge dem på sociale medier.', 'centershop_txtdomain'),
                            $shop ? $shop->post_title : __('din butik', 'centershop_txtdomain')
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <div class="centershop-planner-container">
                <!-- Calendar Header -->
                <div class="centershop-planner-header">
                    <div class="centershop-planner-nav">
                        <button type="button" class="button" id="centershop-prev-month">
                            &larr; <?php _e('Forrige', 'centershop_txtdomain'); ?>
                        </button>
                        <h2 id="centershop-current-month"></h2>
                        <button type="button" class="button" id="centershop-next-month">
                            <?php _e('Næste', 'centershop_txtdomain'); ?> &rarr;
                        </button>
                    </div>
                    
                    <?php if (!$is_shop_manager): ?>
                    <div class="centershop-planner-filters">
                        <select id="centershop-filter-shop">
                            <option value=""><?php _e('Alle butikker', 'centershop_txtdomain'); ?></option>
                            <option value="0"><?php _e('Center generelt', 'centershop_txtdomain'); ?></option>
                        </select>
                        
                        <select id="centershop-filter-status">
                            <option value=""><?php _e('Alle statusser', 'centershop_txtdomain'); ?></option>
                            <option value="draft"><?php _e('Kladde', 'centershop_txtdomain'); ?></option>
                            <option value="awaiting_content"><?php _e('Afventer indhold', 'centershop_txtdomain'); ?></option>
                            <option value="ready"><?php _e('Klar', 'centershop_txtdomain'); ?></option>
                            <option value="published"><?php _e('Publiceret', 'centershop_txtdomain'); ?></option>
                        </select>
                        
                        <select id="centershop-filter-type">
                            <option value=""><?php _e('Alle typer', 'centershop_txtdomain'); ?></option>
                            <option value="post"><?php _e('Opslag', 'centershop_txtdomain'); ?></option>
                            <option value="reel"><?php _e('Reel', 'centershop_txtdomain'); ?></option>
                            <option value="story"><?php _e('Story', 'centershop_txtdomain'); ?></option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Calendar Grid -->
                <div class="centershop-calendar-container">
                    <div class="centershop-calendar-weekdays">
                        <div class="centershop-weekday"><?php _e('Man', 'centershop_txtdomain'); ?></div>
                        <div class="centershop-weekday"><?php _e('Tir', 'centershop_txtdomain'); ?></div>
                        <div class="centershop-weekday"><?php _e('Ons', 'centershop_txtdomain'); ?></div>
                        <div class="centershop-weekday"><?php _e('Tor', 'centershop_txtdomain'); ?></div>
                        <div class="centershop-weekday"><?php _e('Fre', 'centershop_txtdomain'); ?></div>
                        <div class="centershop-weekday centershop-weekend"><?php _e('Lør', 'centershop_txtdomain'); ?></div>
                        <div class="centershop-weekday centershop-weekend"><?php _e('Søn', 'centershop_txtdomain'); ?></div>
                    </div>
                    <div class="centershop-calendar-grid" id="centershop-calendar-grid">
                        <!-- Generated by JavaScript -->
                    </div>
                </div>
                
                <!-- Legend -->
                <div class="centershop-planner-legend">
                    <span class="centershop-legend-item">
                        <span class="centershop-status-dot status-draft"></span> <?php _e('Kladde', 'centershop_txtdomain'); ?>
                    </span>
                    <span class="centershop-legend-item">
                        <span class="centershop-status-dot status-awaiting_content"></span> <?php _e('Afventer indhold', 'centershop_txtdomain'); ?>
                    </span>
                    <span class="centershop-legend-item">
                        <span class="centershop-status-dot status-ready"></span> <?php _e('Klar', 'centershop_txtdomain'); ?>
                    </span>
                    <span class="centershop-legend-item">
                        <span class="centershop-status-dot status-published"></span> <?php _e('Publiceret', 'centershop_txtdomain'); ?>
                    </span>
                </div>
            </div>
            
            <?php if ($is_shop_manager): ?>
            <!-- Shop Upload Section -->
            <div class="centershop-shop-upload-section">
                <h2><?php _e('Upload materiale', 'centershop_txtdomain'); ?></h2>
                <p class="description">
                    <?php _e('Upload billeder og video som centret kan bruge til sociale medier. Du kan tilføje en beskrivelse af hvad billedet viser.', 'centershop_txtdomain'); ?>
                </p>
                
                <div class="centershop-upload-form">
                    <div class="centershop-upload-dropzone" id="centershop-upload-dropzone">
                        <p><?php _e('Træk filer hertil eller klik for at vælge', 'centershop_txtdomain'); ?></p>
                        <input type="file" id="centershop-file-input" multiple accept="image/*,video/*" style="display:none;">
                    </div>
                    
                    <div class="centershop-upload-preview" id="centershop-upload-preview">
                        <!-- Preview items added by JS -->
                    </div>
                    
                    <div class="centershop-upload-description">
                        <label for="centershop-upload-desc"><?php _e('Beskrivelse (valgfri)', 'centershop_txtdomain'); ?></label>
                        <textarea id="centershop-upload-desc" rows="3" placeholder="<?php _e('Beskriv hvad billedet/videoen viser...', 'centershop_txtdomain'); ?>"></textarea>
                    </div>
                    
                    <button type="button" class="button button-primary" id="centershop-submit-upload" disabled>
                        <?php _e('Send til centret', 'centershop_txtdomain'); ?>
                    </button>
                </div>
                
                <!-- Previous uploads -->
                <div class="centershop-previous-uploads">
                    <h3><?php _e('Dine tidligere uploads', 'centershop_txtdomain'); ?></h3>
                    <div id="centershop-uploads-list">
                        <?php $this->render_shop_uploads($user_shop_id); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Post Modal (for quick view/edit) -->
        <div id="centershop-post-modal" class="centershop-modal" style="display:none;">
            <div class="centershop-modal-content">
                <span class="centershop-modal-close">&times;</span>
                <div class="centershop-modal-body">
                    <!-- Content loaded by JS -->
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render shop uploads
     */
    private function render_shop_uploads($shop_id) {
        $posts = get_posts(array(
            'post_type' => CenterShop_Planner_Post_Type::POST_TYPE,
            'meta_key' => '_centershop_shop_id',
            'meta_value' => $shop_id,
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        if (empty($posts)) {
            echo '<p>' . __('Du har ikke uploadet noget endnu.', 'centershop_txtdomain') . '</p>';
            return;
        }
        
        echo '<div class="centershop-uploads-grid">';
        foreach ($posts as $post) {
            $status = get_post_meta($post->ID, '_centershop_status', true) ?: 'draft';
            $media_ids = get_post_meta($post->ID, '_centershop_media_ids', true);
            $media_ids = is_array($media_ids) ? $media_ids : array();
            $scheduled = get_post_meta($post->ID, '_centershop_scheduled_date', true);
            
            $status_labels = array(
                'draft' => __('Modtaget', 'centershop_txtdomain'),
                'awaiting_content' => __('Afventer', 'centershop_txtdomain'),
                'ready' => __('Klar til publicering', 'centershop_txtdomain'),
                'published' => __('Publiceret', 'centershop_txtdomain'),
            );
            
            echo '<div class="centershop-upload-item">';
            
            // Show first media
            if (!empty($media_ids)) {
                $first_media = $media_ids[0];
                if (wp_attachment_is('video', $first_media)) {
                    echo '<div class="centershop-upload-thumb"><video src="' . esc_url(wp_get_attachment_url($first_media)) . '"></video></div>';
                } else {
                    echo '<div class="centershop-upload-thumb"><img src="' . esc_url(wp_get_attachment_image_url($first_media, 'thumbnail')) . '"></div>';
                }
            }
            
            echo '<div class="centershop-upload-info">';
            echo '<span class="centershop-upload-status status-' . esc_attr($status) . '">' . esc_html($status_labels[$status] ?? $status) . '</span>';
            if ($scheduled) {
                echo '<span class="centershop-upload-date">' . esc_html(date_i18n('j. F Y', strtotime($scheduled))) . '</span>';
            }
            echo '</div>';
            
            echo '</div>';
        }
        echo '</div>';
    }
}
