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
        add_action('admin_init', array($this, 'handle_flush_rewrites'));
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

        register_setting('centershop_settings', 'centershop_shop_slug', array(
            'type' => 'string',
            'default' => 'butiksside',
            'sanitize_callback' => 'centershop_sanitize_shop_slug'
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

        add_settings_field(
            'centershop_shop_slug',
            __('Permalink-slug', 'centershop_txtdomain'),
            array($this, 'render_shop_slug_field'),
            'centershop_settings',
            'centershop_general_section'
        );
    }
    
    /**
     * Render general section description
     */
    public function render_general_section() {
        echo '<p>' . esc_html__('Konfigurer generelle indstillinger for CenterShop-pluginet. Hold musen over hjælpeikonet (?) ved hvert felt for en kort forklaring, eller brug hjælpefanen øverst til højre for detaljerede beskrivelser.', 'centershop_txtdomain') . '</p>';
    }

    /**
     * Render plugin name field
     */
    public function render_plugin_name_field() {
        $value = get_option('centershop_plugin_name', 'CenterShop');
        ?>
        <input type="text"
               id="centershop_plugin_name"
               name="centershop_plugin_name"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               aria-describedby="centershop_plugin_name_desc" />
        <span class="dashicons dashicons-editor-help centershop-field-tip"
              title="<?php esc_attr_e('Det navn, der vises i admin-navigationen. Standard er "CenterShop".', 'centershop_txtdomain'); ?>"
              aria-label="<?php esc_attr_e('Hjælp til Plugin navn', 'centershop_txtdomain'); ?>"></span>
        <p class="description" id="centershop_plugin_name_desc">
            <?php esc_html_e('Vises i admin-navigationen. Standard: CenterShop.', 'centershop_txtdomain'); ?>
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
               id="centershop_contact_email"
               name="centershop_contact_email"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               aria-describedby="centershop_contact_email_desc" />
        <span class="dashicons dashicons-editor-help centershop-field-tip"
              title="<?php esc_attr_e('Den e-mailadresse, der modtager systemnotifikationer fra pluginet.', 'centershop_txtdomain'); ?>"
              aria-label="<?php esc_attr_e('Hjælp til Kontakt e-mail', 'centershop_txtdomain'); ?>"></span>
        <p class="description" id="centershop_contact_email_desc">
            <?php esc_html_e('Modtager systemnotifikationer fra CenterShop. Standard er webstedets administrator-e-mail.', 'centershop_txtdomain'); ?>
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
               id="centershop_google_maps_api_key"
               name="centershop_google_maps_api_key"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               aria-describedby="centershop_google_maps_desc" />
        <span class="dashicons dashicons-editor-help centershop-field-tip"
              title="<?php esc_attr_e('Kræves for at vise kortfunktioner. Hent en nøgle via Google Cloud Console.', 'centershop_txtdomain'); ?>"
              aria-label="<?php esc_attr_e('Hjælp til Google Maps API nøgle', 'centershop_txtdomain'); ?>"></span>
        <p class="description" id="centershop_google_maps_desc">
            <?php
            printf(
                /* translators: %s: link to Google Cloud Console */
                esc_html__('Nødvendig for kortvisning (valgfrit). Hent en nøgle på %s.', 'centershop_txtdomain'),
                '<a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">Google Cloud Console</a>'
            );
            ?>
        </p>
        <?php
    }

    /**
     * Render the shop permalink slug field.
     */
    public function render_shop_slug_field() {
        $value = centershop_get_shop_slug();
        $example = trailingslashit( home_url( $value ) ) . 'butiksnavn/';
        ?>
        <input type="text"
               id="centershop_shop_slug"
               name="centershop_shop_slug"
               value="<?php echo esc_attr( $value ); ?>"
               class="regular-text"
               maxlength="100"
               autocapitalize="off"
               spellcheck="false"
               aria-describedby="centershop_shop_slug_desc" />
        <span class="dashicons dashicons-editor-help centershop-field-tip"
              title="<?php esc_attr_e( 'Stien foran butikkens navn i webadressen. Eksempler: butik, butiksside, lokale, store, location.', 'centershop_txtdomain' ); ?>"
              aria-label="<?php esc_attr_e( 'Hjælp til Permalink-slug', 'centershop_txtdomain' ); ?>"></span>
        <p class="description" id="centershop_shop_slug_desc">
            <?php
            printf(
                /* translators: %s: example shop URL */
                esc_html__( 'Sti i butikkens webadresse, fx %s. Brug små bogstaver, tal og bindestreger. Eksempler: butik, butiksside, lokale, store, location.', 'centershop_txtdomain' ),
                '<code>' . esc_html( $example ) . '</code>'
            );
            ?>
        </p>
        <p class="description">
            <?php esc_html_e( 'Gem indstillingerne, når du ændrer sluggen. Permalinks opdateres automatisk bagefter. Brug knappen nedenfor, hvis adresserne stadig ikke virker.', 'centershop_txtdomain' ); ?>
        </p>
        <?php
    }

    /**
     * Flush rewrite rules from the settings page.
     */
    public function handle_flush_rewrites() {
        if ( ! isset( $_POST['centershop_flush_rewrites'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Du har ikke adgang til at genopfriske permalinks.', 'centershop_txtdomain' ) );
        }

        check_admin_referer( 'centershop_flush_rewrites', 'centershop_flush_rewrites_nonce' );

        centershop_flush_butiksside_rewrites();

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'              => 'centershop-settings',
                    'rewrites-flushed'  => '1',
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    /**
     * Separate form so the flush action is not saved as a setting.
     */
    public function render_flush_rewrites_form() {
        ?>
        <form id="centershop-flush-rewrites" method="post" action="">
            <?php wp_nonce_field( 'centershop_flush_rewrites', 'centershop_flush_rewrites_nonce' ); ?>
            <h2><?php esc_html_e( 'Permalinks', 'centershop_txtdomain' ); ?></h2>
            <p>
                <?php esc_html_e( 'Genindlæs rewrite-reglerne i WordPress, så den gemte slug bruges i butikkernes adresser.', 'centershop_txtdomain' ); ?>
            </p>
            <?php
            submit_button(
                __( 'Genopfrisk permalinks', 'centershop_txtdomain' ),
                'secondary',
                'centershop_flush_rewrites',
                false
            );
            ?>
        </form>
        <?php
    }
}
