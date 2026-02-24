<?php
/**
 * Instagram Feed - Settings
 * 
 * Indstillinger for Instagram feed
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_IG_Settings {
    
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
        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Add submenu page
     */
    public function add_submenu_page() {
        add_submenu_page(
            'centershop',
            __('Instagram Feed', 'centershop_txtdomain'),
            __('Instagram Feed', 'centershop_txtdomain'),
            'manage_options',
            'centershop-instagram',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('centershop_instagram', 'centershop_ig_access_token');
        register_setting('centershop_instagram', 'centershop_ig_import_limit', array(
            'type' => 'integer',
            'default' => 10
        ));
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'centershop_page_centershop-instagram') {
            return;
        }
        
        wp_enqueue_script(
            'centershop-ig-settings',
            CENTERSHOP_PLUGIN_URL . 'js/ig-settings.js',
            array('jquery'),
            CENTERSHOP_VERSION,
            true
        );
        
        wp_localize_script('centershop-ig-settings', 'centershopIG', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('centershop_ig_import')
        ));
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $access_token = get_option('centershop_ig_access_token', '');
        $import_limit = get_option('centershop_ig_import_limit', 10);
        $last_import = get_option('centershop_ig_last_import');
        $next_import = CenterShop_IG_Cron::get_next_scheduled();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Instagram Feed Indstillinger', 'centershop_txtdomain'); ?></h1>
            
            <div class="card" style="max-width: 800px;">
                <h2><?php _e('API Konfiguration', 'centershop_txtdomain'); ?></h2>
                
                <form method="post" action="options.php">
                    <?php settings_fields('centershop_instagram'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="centershop_ig_access_token">
                                    <?php _e('Instagram Access Token', 'centershop_txtdomain'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="centershop_ig_access_token" 
                                       name="centershop_ig_access_token" 
                                       value="<?php echo esc_attr($access_token); ?>" 
                                       class="regular-text"
                                       placeholder="<?php _e('Indsæt dit Instagram access token', 'centershop_txtdomain'); ?>">
                                <p class="description">
                                    <?php _e('Få et access token fra Instagram Basic Display API eller Instagram Graph API.', 'centershop_txtdomain'); ?>
                                    <a href="https://developers.facebook.com/docs/instagram-basic-display-api/getting-started" target="_blank">
                                        <?php _e('Læs vejledning', 'centershop_txtdomain'); ?>
                                    </a>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="centershop_ig_import_limit">
                                    <?php _e('Antal posts pr. import', 'centershop_txtdomain'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="centershop_ig_import_limit" 
                                       name="centershop_ig_import_limit" 
                                       value="<?php echo esc_attr($import_limit); ?>" 
                                       min="1" 
                                       max="50" 
                                       class="small-text">
                                <p class="description">
                                    <?php _e('Hvor mange posts skal importeres pr. butik ved hver import?', 'centershop_txtdomain'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(); ?>
                </form>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2><?php _e('Import Status', 'centershop_txtdomain'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Sidste import:', 'centershop_txtdomain'); ?></th>
                        <td>
                            <?php if ($last_import && isset($last_import['time'])): ?>
                                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_import['time']))); ?>
                                <br>
                                <span class="description">
                                    <?php printf(__('%d posts importeret', 'centershop_txtdomain'), $last_import['imported'] ?? 0); ?>
                                    <?php if (!empty($last_import['errors'])): ?>
                                        - <span style="color: #d63638;"><?php printf(__('%d fejl', 'centershop_txtdomain'), count($last_import['errors'])); ?></span>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <em><?php _e('Aldrig importeret', 'centershop_txtdomain'); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Næste import:', 'centershop_txtdomain'); ?></th>
                        <td>
                            <?php if ($next_import): ?>
                                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_import)); ?>
                            <?php else: ?>
                                <em><?php _e('Ikke planlagt', 'centershop_txtdomain'); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Manuel import:', 'centershop_txtdomain'); ?></th>
                        <td>
                            <button type="button" class="button button-secondary" id="centershop-ig-import-now">
                                <?php _e('Importer nu', 'centershop_txtdomain'); ?>
                            </button>
                            <span class="spinner" style="float: none; margin: 0 10px;"></span>
                            <span id="centershop-ig-import-result"></span>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2><?php _e('Butik Konfiguration', 'centershop_txtdomain'); ?></h2>
                <p>
                    <?php _e('For hver butik skal du konfigurere Instagram bruger-ID og access token i butikkens indstillinger.', 'centershop_txtdomain'); ?>
                </p>
                <p>
                    <?php _e('Gå til hver butik og angiv:', 'centershop_txtdomain'); ?>
                </p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><strong>Instagram Bruger ID</strong> (meta: <code>butik_payed_ig_user_id</code>)</li>
                    <li><strong>Instagram Token</strong> (meta: <code>butik_payed_ig_token</code>)</li>
                </ul>
            </div>
        </div>
        <?php
    }
}
