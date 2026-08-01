<?php
/**
 * Centralt Admin Menu System for CenterShop
 * 
 * Håndterer hovedmenu og undermenuer for butiksfunktionalitet.
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
        add_action('current_screen', array($this, 'add_help_tabs'));
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
                    <p class="centershop-stat"><?php echo esc_html( $shop_count->publish ); ?></p>
                    <p><?php _e('aktive butikker', 'centershop_txtdomain'); ?></p>
                    <a href="<?php echo esc_url( admin_url('edit.php?post_type=butiksside') ); ?>" class="button">
                        <?php _e('Se alle', 'centershop_txtdomain'); ?>
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
                esc_html( human_time_diff($activity['time']) . ' ' . __('siden', 'centershop_txtdomain') )
            );
        }
        echo '</ul>';
    }
    
    /**
     * Add contextual help tabs to CenterShop admin pages.
     */
    public function add_help_tabs($screen) {
        if (strpos($screen->id, self::MENU_SLUG) === false) {
            return;
        }

        // --- Settings page ---
        if ($screen->id === 'centershop_page_' . self::MENU_SLUG . '-settings') {
            $screen->add_help_tab(array(
                'id'      => 'centershop_settings_overview',
                'title'   => __('Oversigt', 'centershop_txtdomain'),
                'content' =>
                    '<p>' . __('På denne side konfigurerer du de overordnede indstillinger for CenterShop-pluginet.', 'centershop_txtdomain') . '</p>' .
                    '<p>' . __('Klik på "Gem indstillinger" for at gemme ændringerne.', 'centershop_txtdomain') . '</p>',
            ));

            $screen->add_help_tab(array(
                'id'      => 'centershop_settings_fields',
                'title'   => __('Feltbeskrivelser', 'centershop_txtdomain'),
                'content' =>
                    '<p><strong>' . __('Plugin navn', 'centershop_txtdomain') . '</strong> &ndash; ' .
                    __('Det navn, der vises i admin-navigationen. Standard er "CenterShop".', 'centershop_txtdomain') . '</p>' .
                    '<p><strong>' . __('Kontakt e-mail', 'centershop_txtdomain') . '</strong> &ndash; ' .
                    __('Den e-mailadresse, der modtager systemnotifikationer fra pluginet.', 'centershop_txtdomain') . '</p>' .
                    '<p><strong>' . __('Google Maps API nøgle', 'centershop_txtdomain') . '</strong> &ndash; ' .
                    __('Kræves for at vise centrets kort. Hent en nøgle via Google Cloud Console.', 'centershop_txtdomain') . '</p>',
            ));

            $screen->set_help_sidebar(
                '<p><strong>' . __('Nyttige links', 'centershop_txtdomain') . '</strong></p>' .
                '<p><a href="https://console.cloud.google.com/" target="_blank">' . __('Google Cloud Console', 'centershop_txtdomain') . '</a></p>'
            );
        }

        // --- Opening hours page ---
        if ($screen->id === 'centershop_page_centershop-opening-hours') {
            $screen->add_help_tab(array(
                'id'      => 'centershop_hours_overview',
                'title'   => __('Om åbningstider', 'centershop_txtdomain'),
                'content' =>
                    '<p>' . __('Her angiver du centrets generelle åbningstider for alle ugens dage samt helligdage.', 'centershop_txtdomain') . '</p>' .
                    '<p>' . __('Individuelle butikker kan vælge at arve disse tider eller angive egne åbningstider.', 'centershop_txtdomain') . '</p>',
            ));

            $screen->add_help_tab(array(
                'id'      => 'centershop_hours_howto',
                'title'   => __('Brug af formularen', 'centershop_txtdomain'),
                'content' =>
                    '<p><strong>' . __('Åbner / Lukker', 'centershop_txtdomain') . '</strong> &ndash; ' .
                    __('Angiv tidspunktet i formatet TT:MM, f.eks. 10:00 og 18:00.', 'centershop_txtdomain') . '</p>' .
                    '<p><strong>' . __('Helt lukket', 'centershop_txtdomain') . '</strong> &ndash; ' .
                    __('Afkryds dette felt, hvis centret er lukket hele dagen.', 'centershop_txtdomain') . '</p>' .
                    '<p><strong>' . __('Ekstra tekst', 'centershop_txtdomain') . '</strong> &ndash; ' .
                    __('Valgfri besked der vises under åbningstiderne, f.eks. ved ændrede tider i ferier.', 'centershop_txtdomain') . '</p>' .
                    '<p><strong>' . __('Helligdage', 'centershop_txtdomain') . '</strong> &ndash; ' .
                    __('Åbningstider for de danske helligdage beregnes automatisk for indeværende år.', 'centershop_txtdomain') . '</p>',
            ));
        }
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
                submit_button(__('Gem indstillinger', 'centershop_txtdomain'));
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
