<?php
/**
 * Floor Plan Management Class
 * 
 * Håndterer upload og administration af SVG grundplan samt 
 * mapping af butikslokaler til butikker
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_FloorPlan {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Option name for floor plan data
     */
    const OPTION_NAME = 'centershop_floorplan_data';
    const OPTION_SVG = 'centershop_floorplan_svg';
    
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
        add_action('admin_menu', array($this, 'add_admin_menu'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_save_floorplan_svg', array($this, 'ajax_save_svg'));
        add_action('wp_ajax_save_floorplan_areas', array($this, 'ajax_save_areas'));
        add_action('wp_ajax_get_floorplan_data', array($this, 'ajax_get_data'));
        add_action('wp_ajax_delete_floorplan_area', array($this, 'ajax_delete_area'));
        
        // Frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // Shortcode
        add_shortcode('centershop_floorplan', array($this, 'floorplan_shortcode'));
        
        // AJAX for frontend
        add_action('wp_ajax_search_shops', array($this, 'ajax_search_shops'));
        add_action('wp_ajax_nopriv_search_shops', array($this, 'ajax_search_shops'));
        
        // Enable SVG uploads
        add_filter('upload_mimes', array($this, 'allow_svg_upload'));
        add_filter('wp_check_filetype_and_ext', array($this, 'fix_svg_mime_type'), 10, 4);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            CenterShop_Admin_Menu::MENU_SLUG,
            __('Grundplan', 'centershop_txtdomain'),
            __('Grundplan', 'centershop_txtdomain'),
            'manage_options',
            'centershop-floorplan',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'centershop_page_centershop-floorplan') {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_style(
            'centershop-floorplan-admin',
            CENTERSHOP_PLUGIN_URL . 'css/floorplan-admin.css',
            array(),
            CENTERSHOP_VERSION
        );
        
        wp_enqueue_script(
            'centershop-floorplan-admin',
            CENTERSHOP_PLUGIN_URL . 'js/floorplan-admin.js',
            array('jquery', 'jquery-ui-sortable'),
            CENTERSHOP_VERSION,
            true
        );
        
        wp_localize_script('centershop-floorplan-admin', 'centershopFloorplan', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('centershop_floorplan_nonce'),
            'strings' => array(
                'selectShop' => __('Vælg butik', 'centershop_txtdomain'),
                'deleteArea' => __('Slet område', 'centershop_txtdomain'),
                'confirmDelete' => __('Er du sikker på at du vil slette dette område?', 'centershop_txtdomain'),
                'saved' => __('Grundplan gemt!', 'centershop_txtdomain'),
                'error' => __('Der opstod en fejl', 'centershop_txtdomain'),
            )
        ));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_register_style(
            'centershop-floorplan',
            CENTERSHOP_PLUGIN_URL . 'css/floorplan-frontend.css',
            array(),
            CENTERSHOP_VERSION
        );
        
        wp_register_script(
            'centershop-floorplan',
            CENTERSHOP_PLUGIN_URL . 'js/floorplan-frontend.js',
            array('jquery'),
            CENTERSHOP_VERSION,
            true
        );
        
        wp_localize_script('centershop-floorplan', 'centershopFloorplanFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'strings' => array(
                'searchPlaceholder' => __('Søg efter butik...', 'centershop_txtdomain'),
                'noResults' => __('Ingen butikker fundet', 'centershop_txtdomain'),
            )
        ));
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $svg_url = get_option(self::OPTION_SVG, '');
        $areas = $this->get_areas();
        $shops = $this->get_all_shops();
        
        ?>
        <div class="wrap centershop-floorplan-admin">
            <h1><?php _e('Grundplan over centret', 'centershop_txtdomain'); ?></h1>
            
            <div class="centershop-floorplan-content">
                <div class="floorplan-sidebar">
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Upload SVG grundplan', 'centershop_txtdomain'); ?></h2>
                        <div class="inside">
                            <button type="button" class="button button-primary" id="upload-svg-btn">
                                <?php _e('Vælg SVG fil', 'centershop_txtdomain'); ?>
                            </button>
                            <p class="description">
                                <?php _e('Upload en SVG-fil af centerets grundplan. Du kan derefter definere klikbare områder.', 'centershop_txtdomain'); ?>
                            </p>
                            <?php if ($svg_url): ?>
                                <p class="svg-uploaded">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('SVG uploadet', 'centershop_txtdomain'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Definerede områder', 'centershop_txtdomain'); ?></h2>
                        <div class="inside">
                            <div id="areas-list">
                                <?php if (empty($areas)): ?>
                                    <p class="no-areas"><?php _e('Ingen områder defineret endnu. Klik på SVG\'en for at tilføje områder.', 'centershop_txtdomain'); ?></p>
                                <?php else: ?>
                                    <?php foreach ($areas as $index => $area): ?>
                                        <div class="area-item" data-index="<?php echo $index; ?>">
                                            <strong><?php echo esc_html($area['label']); ?></strong>
                                            <?php if (!empty($area['shop_id'])): ?>
                                                <span class="shop-name">(<?php echo get_the_title($area['shop_id']); ?>)</span>
                                            <?php endif; ?>
                                            <button type="button" class="delete-area" data-index="<?php echo $index; ?>">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button" id="add-area-btn">
                                <span class="dashicons dashicons-plus-alt"></span>
                                <?php _e('Tilføj område', 'centershop_txtdomain'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Område detaljer', 'centershop_txtdomain'); ?></h2>
                        <div class="inside">
                            <div id="area-editor">
                                <p class="description"><?php _e('Klik på et område i grundplanen for at redigere det.', 'centershop_txtdomain'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="floorplan-main">
                    <div class="floorplan-canvas-wrapper">
                        <?php if ($svg_url): ?>
                            <div id="floorplan-canvas" data-mode="edit">
                                <img src="<?php echo esc_url($svg_url); ?>" alt="<?php _e('Grundplan', 'centershop_txtdomain'); ?>" id="floorplan-svg">
                                <svg id="floorplan-overlay">
                                    <?php foreach ($areas as $index => $area): ?>
                                        <polygon 
                                            class="floorplan-area" 
                                            data-index="<?php echo $index; ?>"
                                            data-shop-id="<?php echo esc_attr($area['shop_id'] ?? ''); ?>"
                                            points="<?php echo esc_attr($area['points']); ?>"
                                            fill="rgba(52, 152, 219, 0.3)"
                                            stroke="#3498db"
                                            stroke-width="2"
                                        />
                                    <?php endforeach; ?>
                                </svg>
                            </div>
                        <?php else: ?>
                            <div class="floorplan-empty">
                                <span class="dashicons dashicons-images-alt2"></span>
                                <p><?php _e('Upload en SVG grundplan for at komme i gang', 'centershop_txtdomain'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="floorplan-actions">
                        <button type="button" class="button button-primary button-large" id="save-floorplan">
                            <?php _e('Gem grundplan', 'centershop_txtdomain'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Template for area editor -->
        <script type="text/template" id="area-editor-template">
            <div class="area-form">
                <p>
                    <label>
                        <strong><?php _e('Område navn:', 'centershop_txtdomain'); ?></strong><br>
                        <input type="text" class="widefat area-label" value="{{label}}" placeholder="<?php _e('F.eks. Butikslokale A1', 'centershop_txtdomain'); ?>">
                    </label>
                </p>
                <p>
                    <label>
                        <strong><?php _e('Tilknyt butik:', 'centershop_txtdomain'); ?></strong><br>
                        <select class="widefat area-shop">
                            <option value=""><?php _e('Ingen butik', 'centershop_txtdomain'); ?></option>
                            <?php foreach ($shops as $shop): ?>
                                <option value="<?php echo $shop->ID; ?>" {{selected_<?php echo $shop->ID; ?>}}>
                                    <?php echo esc_html($shop->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </p>
                <p>
                    <label>
                        <strong><?php _e('Farve:', 'centershop_txtdomain'); ?></strong><br>
                        <input type="color" class="area-color" value="{{color}}">
                    </label>
                </p>
                <p>
                    <button type="button" class="button button-primary update-area"><?php _e('Opdater område', 'centershop_txtdomain'); ?></button>
                    <button type="button" class="button delete-area-btn"><?php _e('Slet område', 'centershop_txtdomain'); ?></button>
                </p>
            </div>
        </script>
        <?php
    }
    
    /**
     * Get all shops
     */
    private function get_all_shops() {
        return get_posts(array(
            'post_type' => 'butiksside',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish'
        ));
    }
    
    /**
     * Get areas
     */
    private function get_areas() {
        $areas = get_option(self::OPTION_NAME, array());
        return is_array($areas) ? $areas : array();
    }
    
    /**
     * AJAX: Save SVG
     */
    public function ajax_save_svg() {
        check_ajax_referer('centershop_floorplan_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        $url = wp_get_attachment_url($attachment_id);
        
        if (!$url) {
            wp_send_json_error('Invalid attachment');
        }
        
        // Verify it's an SVG file
        $filetype = wp_check_filetype($url);
        if (!in_array($filetype['type'], array('image/svg+xml', 'image/svg'))) {
            wp_send_json_error('File must be an SVG');
        }
        
        update_option(self::OPTION_SVG, $url);
        
        wp_send_json_success(array('url' => $url));
    }
    
    /**
     * AJAX: Save areas
     */
    public function ajax_save_areas() {
        check_ajax_referer('centershop_floorplan_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $areas = json_decode(stripslashes($_POST['areas']), true);
        
        if (!is_array($areas)) {
            wp_send_json_error('Invalid data');
        }
        
        update_option(self::OPTION_NAME, $areas);
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Get data
     */
    public function ajax_get_data() {
        check_ajax_referer('centershop_floorplan_nonce', 'nonce');
        
        wp_send_json_success(array(
            'svg' => get_option(self::OPTION_SVG, ''),
            'areas' => $this->get_areas()
        ));
    }
    
    /**
     * AJAX: Delete area
     */
    public function ajax_delete_area() {
        check_ajax_referer('centershop_floorplan_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $index = intval($_POST['index']);
        $areas = $this->get_areas();
        
        if (isset($areas[$index])) {
            array_splice($areas, $index, 1);
            update_option(self::OPTION_NAME, $areas);
            wp_send_json_success();
        }
        
        wp_send_json_error('Area not found');
    }
    
    /**
     * AJAX: Search shops (frontend)
     */
    public function ajax_search_shops() {
        $search = sanitize_text_field($_GET['s'] ?? '');
        
        $shops = get_posts(array(
            'post_type' => 'butiksside',
            'posts_per_page' => 10,
            's' => $search,
            'post_status' => 'publish'
        ));
        
        $results = array();
        foreach ($shops as $shop) {
            $results[] = array(
                'id' => $shop->ID,
                'title' => $shop->post_title,
                'url' => get_permalink($shop->ID)
            );
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Allow SVG uploads
     */
    public function allow_svg_upload($mimes) {
        $mimes['svg'] = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        return $mimes;
    }
    
    /**
     * Fix SVG mime type detection
     */
    public function fix_svg_mime_type($data, $file, $filename, $mimes) {
        $ext = isset($data['ext']) ? $data['ext'] : '';
        
        if (strlen($ext) < 1) {
            $exploded = explode('.', $filename);
            $ext = strtolower(end($exploded));
        }
        
        if ($ext === 'svg') {
            $data['type'] = 'image/svg+xml';
            $data['ext'] = 'svg';
        } elseif ($ext === 'svgz') {
            $data['type'] = 'image/svg+xml';
            $data['ext'] = 'svgz';
        }
        
        return $data;
    }
    
    /**
     * Floorplan shortcode
     */
    public function floorplan_shortcode($atts) {
        $atts = shortcode_atts(array(
            'shop_id' => '',
            'show_search' => 'yes',
            'height' => '600px'
        ), $atts);
        
        wp_enqueue_style('centershop-floorplan');
        wp_enqueue_script('centershop-floorplan');
        
        $svg_url = get_option(self::OPTION_SVG, '');
        $areas = $this->get_areas();
        
        if (empty($svg_url)) {
            return '<p>' . __('Grundplan er ikke uploadet endnu.', 'centershop_txtdomain') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="centershop-floorplan-wrapper" data-selected-shop="<?php echo esc_attr($atts['shop_id']); ?>">
            <?php if ($atts['show_search'] === 'yes'): ?>
                <div class="floorplan-search">
                    <input type="text" 
                           class="floorplan-search-input" 
                           placeholder="<?php _e('Søg efter butik...', 'centershop_txtdomain'); ?>"
                           autocomplete="off">
                    <div class="floorplan-search-results"></div>
                </div>
            <?php endif; ?>
            
            <div class="floorplan-display" style="max-height: <?php echo esc_attr($atts['height']); ?>;">
                <img src="<?php echo esc_url($svg_url); ?>" alt="<?php _e('Grundplan', 'centershop_txtdomain'); ?>" class="floorplan-svg">
                <svg class="floorplan-overlay-svg">
                    <?php foreach ($areas as $index => $area): ?>
                        <polygon 
                            class="floorplan-area-front <?php echo $atts['shop_id'] && $atts['shop_id'] == ($area['shop_id'] ?? '') ? 'selected' : ''; ?>" 
                            data-shop-id="<?php echo esc_attr($area['shop_id'] ?? ''); ?>"
                            data-shop-name="<?php echo $area['shop_id'] ? esc_attr(get_the_title($area['shop_id'])) : ''; ?>"
                            points="<?php echo esc_attr($area['points']); ?>"
                            fill="<?php echo esc_attr($area['color'] ?? 'rgba(52, 152, 219, 0.3)'); ?>"
                            stroke="#3498db"
                            stroke-width="2"
                        />
                    <?php endforeach; ?>
                </svg>
            </div>
            
            <div class="floorplan-info" style="display: none;">
                <h3 class="shop-name"></h3>
                <a href="#" class="shop-link button"><?php _e('Besøg butik', 'centershop_txtdomain'); ?></a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
