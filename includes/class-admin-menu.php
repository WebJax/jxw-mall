<?php
/**
 * Centralt Admin Menu System for CenterShop
 * 
 * Håndterer hovedmenu og alle undermenuer for:
 * - Butikker
 * - SoMe Planner
 * - Facebook Feed
 * - Butik-adgange
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_Admin_Menu {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Menu slug
     */
    const MENU_SLUG = 'centershop';
    
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
        add_action('admin_menu', array($this, 'register_menus'), 5);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Register all menus
     */
    public function register_menus() {
        // Hovedmenu
        add_menu_page(
            __('CenterShop', 'centershop_txtdomain'),
            __('CenterShop', 'centershop_txtdomain'),
            'edit_posts',
            self::MENU_SLUG,
            array($this, 'render_dashboard'),
            'dashicons-store',
            26
        );
        
        // Dashboard (samme som hovedmenu)
        add_submenu_page(
            self::MENU_SLUG,
            __('Oversigt', 'centershop_txtdomain'),
            __('Oversigt', 'centershop_txtdomain'),
            'edit_posts',
            self::MENU_SLUG,
            array($this, 'render_dashboard')
        );
        
        // Butikker - link til eksisterende CPT
        add_submenu_page(
            self::MENU_SLUG,
            __('Alle butikker', 'centershop_txtdomain'),
            __('Alle butikker', 'centershop_txtdomain'),
            'edit_posts',
            'edit.php?post_type=butiksside'
        );
        
        // Opret ny butik
        add_submenu_page(
            self::MENU_SLUG,
            __('Opret ny butik', 'centershop_txtdomain'),
            __('Opret ny butik', 'centershop_txtdomain'),
            'edit_posts',
            'post-new.php?post_type=butiksside'
        );
        
        // Kategorier
        add_submenu_page(
            self::MENU_SLUG,
            __('Kategorier', 'centershop_txtdomain'),
            __('Kategorier', 'centershop_txtdomain'),
            'manage_categories',
            'edit-tags.php?taxonomy=category&post_type=butiksside'
        );
        
        // Åbningstider
        add_submenu_page(
            self::MENU_SLUG,
            __('Åbningstider', 'centershop_txtdomain'),
            __('Åbningstider', 'centershop_txtdomain'),
            'manage_options',
            'centershop-opening-hours',
            array($this, 'render_opening_hours_redirect')
        );
        
        // Eksporter E-mails
        add_submenu_page(
            self::MENU_SLUG,
            __('Eksporter e-mails', 'centershop_txtdomain'),
            __('Eksporter e-mails', 'centershop_txtdomain'),
            'manage_options',
            'centershop-export-emails',
            array($this, 'render_export_emails_redirect')
        );
        
        // SoMe Planner
        add_submenu_page(
            self::MENU_SLUG,
            __('SoMe Planner', 'centershop_txtdomain'),
            __('SoMe Planner', 'centershop_txtdomain'),
            'edit_posts',
            self::MENU_SLUG . '-planner',
            array($this, 'render_planner_page')
        );
        
        // Facebook Feed
        add_submenu_page(
            self::MENU_SLUG,
            __('Facebook Feed', 'centershop_txtdomain'),
            __('Facebook Feed', 'centershop_txtdomain'),
            'manage_options',
            self::MENU_SLUG . '-facebook',
            array($this, 'render_facebook_page')
        );
        
        // Facebook Posts List
        add_submenu_page(
            self::MENU_SLUG,
            __('Facebook Opslag', 'centershop_txtdomain'),
            __('Facebook Opslag', 'centershop_txtdomain'),
            'manage_options',
            self::MENU_SLUG . '-facebook-posts',
            array($this, 'render_facebook_posts_page')
        );
        
        // Butik-adgange (kun for admins)
        add_submenu_page(
            self::MENU_SLUG,
            __('Butik-adgange', 'centershop_txtdomain'),
            __('Butik-adgange', 'centershop_txtdomain'),
            'manage_options',
            self::MENU_SLUG . '-shop-access',
            array($this, 'render_shop_access_page')
        );
        
        // Indstillinger
        add_submenu_page(
            self::MENU_SLUG,
            __('Indstillinger', 'centershop_txtdomain'),
            __('Indstillinger', 'centershop_txtdomain'),
            'manage_options',
            self::MENU_SLUG . '-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Kun load på CenterShop sider
        if (strpos($hook, self::MENU_SLUG) === false) {
            return;
        }
        
        wp_enqueue_style(
            'centershop-admin',
            plugins_url('/css/centershop-admin.css', dirname(__FILE__)),
            array(),
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'css/centershop-admin.css')
        );
        
        wp_enqueue_script(
            'centershop-admin',
            plugins_url('/js/centershop-admin.js', dirname(__FILE__)),
            array('jquery'),
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'js/centershop-admin.js'),
            true
        );
        
        wp_localize_script('centershop-admin', 'centershopAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('centershop_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Er du sikker?', 'centershop_txtdomain'),
                'saving' => __('Gemmer...', 'centershop_txtdomain'),
                'saved' => __('Gemt!', 'centershop_txtdomain'),
                'error' => __('Der opstod en fejl', 'centershop_txtdomain'),
            )
        ));
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        $current_user = wp_get_current_user();
        $is_shop_manager = in_array('shop_manager', (array) $current_user->roles);
        
        // Hvis butik-bruger, vis deres dashboard
        if ($is_shop_manager) {
            $this->render_shop_dashboard();
            return;
        }
        
        // Admin dashboard
        ?>
        <div class="wrap centershop-dashboard">
            <h1><?php _e('CenterShop Oversigt', 'centershop_txtdomain'); ?></h1>
            
            <div class="centershop-dashboard-widgets">
                <!-- Statistik kort -->
                <div class="centershop-widget">
                    <h2><?php _e('Butikker', 'centershop_txtdomain'); ?></h2>
                    <?php
                    $shop_count = wp_count_posts('butiksside');
                    ?>
                    <p class="centershop-stat"><?php echo $shop_count->publish; ?></p>
                    <p><?php _e('aktive butikker', 'centershop_txtdomain'); ?></p>
                    <a href="<?php echo admin_url('edit.php?post_type=butiksside'); ?>" class="button">
                        <?php _e('Se alle', 'centershop_txtdomain'); ?>
                    </a>
                </div>
                
                <div class="centershop-widget">
                    <h2><?php _e('SoMe Posts', 'centershop_txtdomain'); ?></h2>
                    <?php
                    $post_count = wp_count_posts('centershop_post');
                    $count = isset($post_count->publish) ? $post_count->publish : 0;
                    ?>
                    <p class="centershop-stat"><?php echo $count; ?></p>
                    <p><?php _e('planlagte opslag', 'centershop_txtdomain'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=centershop-planner'); ?>" class="button">
                        <?php _e('Åbn planner', 'centershop_txtdomain'); ?>
                    </a>
                </div>
                
                <div class="centershop-widget">
                    <h2><?php _e('Facebook Feed', 'centershop_txtdomain'); ?></h2>
                    <?php
                    $fb_count = wp_count_posts('centershop_fb_post');
                    $fb_total = isset($fb_count->publish) ? $fb_count->publish : 0;
                    ?>
                    <p class="centershop-stat"><?php echo $fb_total; ?></p>
                    <p><?php _e('importerede opslag', 'centershop_txtdomain'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=centershop-facebook'); ?>" class="button">
                        <?php _e('Indstillinger', 'centershop_txtdomain'); ?>
                    </a>
                </div>
                
                <div class="centershop-widget">
                    <h2><?php _e('Butik-brugere', 'centershop_txtdomain'); ?></h2>
                    <?php
                    $shop_users = get_users(array('role' => 'shop_manager'));
                    ?>
                    <p class="centershop-stat"><?php echo count($shop_users); ?></p>
                    <p><?php _e('butik-logins', 'centershop_txtdomain'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=centershop-shop-access'); ?>" class="button">
                        <?php _e('Administrer', 'centershop_txtdomain'); ?>
                    </a>
                </div>
            </div>
            
            <!-- Seneste aktivitet -->
            <div class="centershop-recent-activity">
                <h2><?php _e('Seneste aktivitet', 'centershop_txtdomain'); ?></h2>
                <?php $this->render_recent_activity(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render shop dashboard for shop_manager role
     */
    private function render_shop_dashboard() {
        $current_user = wp_get_current_user();
        $shop_id = get_user_meta($current_user->ID, 'centershop_shop_id', true);
        $shop = $shop_id ? get_post($shop_id) : null;
        
        ?>
        <div class="wrap centershop-shop-dashboard">
            <h1><?php 
                if ($shop) {
                    printf(__('Velkommen, %s', 'centershop_txtdomain'), $shop->post_title);
                } else {
                    _e('Min butik', 'centershop_txtdomain');
                }
            ?></h1>
            
            <?php if (!$shop): ?>
                <div class="notice notice-warning">
                    <p><?php _e('Din bruger er ikke tilknyttet en butik endnu. Kontakt administrator.', 'centershop_txtdomain'); ?></p>
                </div>
            <?php else: ?>
                <div class="centershop-dashboard-widgets">
                    <div class="centershop-widget">
                        <h2><?php _e('Min butik', 'centershop_txtdomain'); ?></h2>
                        <p><?php echo esc_html($shop->post_title); ?></p>
                        <a href="<?php echo get_edit_post_link($shop_id); ?>" class="button button-primary">
                            <?php _e('Rediger butik', 'centershop_txtdomain'); ?>
                        </a>
                    </div>
                    
                    <div class="centershop-widget">
                        <h2><?php _e('SoMe Planner', 'centershop_txtdomain'); ?></h2>
                        <p><?php _e('Upload billeder og video til centrets SoMe', 'centershop_txtdomain'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=centershop-planner'); ?>" class="button button-primary">
                            <?php _e('Åbn planner', 'centershop_txtdomain'); ?>
                        </a>
                    </div>
                    
                    <div class="centershop-widget">
                        <h2><?php _e('Mine uploads', 'centershop_txtdomain'); ?></h2>
                        <?php
                        $uploads = get_posts(array(
                            'post_type' => 'centershop_post',
                            'meta_key' => '_centershop_shop_id',
                            'meta_value' => $shop_id,
                            'posts_per_page' => -1
                        ));
                        ?>
                        <p class="centershop-stat"><?php echo count($uploads); ?></p>
                        <p><?php _e('uploads i alt', 'centershop_txtdomain'); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render recent activity
     */
    private function render_recent_activity() {
        $activities = array();
        
        // Seneste butikker
        $recent_shops = get_posts(array(
            'post_type' => 'butiksside',
            'posts_per_page' => 3,
            'orderby' => 'modified',
            'order' => 'DESC'
        ));
        
        foreach ($recent_shops as $shop) {
            $activities[] = array(
                'time' => strtotime($shop->post_modified),
                'type' => 'shop',
                'text' => sprintf(__('Butik "%s" blev opdateret', 'centershop_txtdomain'), $shop->post_title),
                'link' => get_edit_post_link($shop->ID)
            );
        }
        
        // Sorter efter tid
        usort($activities, function($a, $b) {
            return $b['time'] - $a['time'];
        });
        
        if (empty($activities)) {
            echo '<p>' . __('Ingen nylig aktivitet', 'centershop_txtdomain') . '</p>';
            return;
        }
        
        echo '<ul class="centershop-activity-list">';
        foreach (array_slice($activities, 0, 10) as $activity) {
            printf(
                '<li><span class="activity-type activity-%s"></span> %s <span class="activity-time">%s</span></li>',
                esc_attr($activity['type']),
                wp_kses_post($activity['text']),
                human_time_diff($activity['time']) . ' ' . __('siden', 'centershop_txtdomain')
            );
        }
        echo '</ul>';
    }
    
    /**
     * Render planner page
     */
    public function render_planner_page() {
        // Loaded via some-planner module
        do_action('centershop_render_planner');
    }
    
    /**
     * Render facebook page
     */
    public function render_facebook_page() {
        // Loaded via facebook-feed module
        do_action('centershop_render_facebook_settings');
    }
    
    /**
     * Render facebook posts page
     */
    public function render_facebook_posts_page() {
        // Loaded via facebook-feed module
        do_action('centershop_render_facebook_posts');
    }
    
    /**
     * Render shop access page
     */
    public function render_shop_access_page() {
        // Loaded via shop-access module
        do_action('centershop_render_shop_access');
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('CenterShop Indstillinger', 'centershop_txtdomain'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('centershop_settings');
                do_settings_sections('centershop_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render opening hours redirect
     */
    public function render_opening_hours_redirect() {
        // Call the existing function from functions-shopping-hours.php
        if (class_exists('CenterShop_Shopping_Hours')) {
            $shopping_hours = new CenterShop_Shopping_Hours();
            $shopping_hours->shoppinghours_setup_settings_page();
        }
    }
    
    /**
     * Render export emails redirect
     */
    public function render_export_emails_redirect() {
        // Call the existing function from jxw-mall.php
        if (function_exists('centershop_export_emails_page')) {
            centershop_export_emails_page();
        }
    }
}
