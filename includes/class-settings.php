<?php
/**
 * CenterShop - General Settings
 * 
 * Handles general plugin-wide settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_Settings {
    
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
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Register settings
        register_setting('centershop_settings', 'centershop_plugin_name', array(
            'type' => 'string',
            'default' => 'CenterShop',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('centershop_settings', 'centershop_google_maps_api_key', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('centershop_settings', 'centershop_contact_email', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_email'
        ));
        
        // Add settings section
        add_settings_section(
            'centershop_general_section',
            __('Generelle indstillinger', 'centershop_txtdomain'),
            array($this, 'render_general_section'),
            'centershop_settings'
        );
        
        // Add settings fields
        add_settings_field(
            'centershop_plugin_name',
            __('Plugin navn', 'centershop_txtdomain'),
            array($this, 'render_plugin_name_field'),
            'centershop_settings',
            'centershop_general_section'
        );
        
        add_settings_field(
            'centershop_contact_email',
            __('Kontakt e-mail', 'centershop_txtdomain'),
            array($this, 'render_contact_email_field'),
            'centershop_settings',
            'centershop_general_section'
        );
        
        add_settings_field(
            'centershop_google_maps_api_key',
            __('Google Maps API nøgle', 'centershop_txtdomain'),
            array($this, 'render_google_maps_field'),
            'centershop_settings',
            'centershop_general_section'
        );
    }
    
    /**
     * Render general section description
     */
    public function render_general_section() {
        echo '<p>' . esc_html__('Konfigurer generelle indstillinger for CenterShop plugin.', 'centershop_txtdomain') . '</p>';
    }
    
    /**
     * Render plugin name field
     */
    public function render_plugin_name_field() {
        $value = get_option('centershop_plugin_name', 'CenterShop');
        ?>
        <input type="text" 
               name="centershop_plugin_name" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <p class="description">
            <?php esc_html_e('Navnet på pluginet som vises i admin interface.', 'centershop_txtdomain'); ?>
        </p>
        <?php
    }
    
    /**
     * Render contact email field
     */
    public function render_contact_email_field() {
        $value = get_option('centershop_contact_email', get_option('admin_email'));
        ?>
        <input type="email" 
               name="centershop_contact_email" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <p class="description">
            <?php esc_html_e('E-mail adresse for kontakt vedrørende CenterShop funktionalitet.', 'centershop_txtdomain'); ?>
        </p>
        <?php
    }
    
    /**
     * Render Google Maps API key field
     */
    public function render_google_maps_field() {
        $value = get_option('centershop_google_maps_api_key', '');
        ?>
        <input type="password" 
               name="centershop_google_maps_api_key" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <p class="description">
            <?php esc_html_e('Google Maps API nøgle til visning af kort (valgfrit).', 'centershop_txtdomain'); ?>
        </p>
        <?php
    }
}
