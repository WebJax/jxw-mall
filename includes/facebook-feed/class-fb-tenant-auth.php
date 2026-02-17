<?php
/**
 * Facebook Feed - Tenant Authentication Handler
 * 
 * Handles OAuth flow for tenants to connect their Facebook pages
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_FB_Tenant_Auth {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Connections handler
     */
    private $connections;
    
    /**
     * API handler
     */
    private $api;
    
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
        $this->connections = CenterShop_FB_Connections::get_instance();
        $this->api = CenterShop_FB_API_Handler::get_instance();
        
        // Register public endpoints
        add_action('init', array($this, 'register_endpoints'));
        add_action('template_redirect', array($this, 'handle_endpoints'));
    }
    
    /**
     * Register rewrite endpoints
     */
    public function register_endpoints() {
        add_rewrite_rule(
            '^connect-facebook/?$',
            'index.php?centershop_fb_connect=landing',
            'top'
        );
        add_rewrite_rule(
            '^connect-facebook/callback/?$',
            'index.php?centershop_fb_connect=callback',
            'top'
        );
        
        add_rewrite_tag('%centershop_fb_connect%', '([^&]+)');
    }
    
    /**
     * Handle endpoint requests
     */
    public function handle_endpoints() {
        $endpoint = get_query_var('centershop_fb_connect');
        
        if (!$endpoint) {
            return;
        }
        
        // Prevent WordPress from loading normal template
        remove_action('template_redirect', 'redirect_canonical');
        
        switch ($endpoint) {
            case 'landing':
                $this->render_landing_page();
                exit;
            
            case 'callback':
                $this->handle_oauth_callback();
                exit;
        }
    }
    
    /**
     * Render landing page
     */
    private function render_landing_page() {
        $shop_id = isset($_GET['shop']) ? intval($_GET['shop']) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        
        // Validate token
        $token_data = $this->connections->validate_magic_token($token);
        
        if (is_wp_error($token_data)) {
            $this->render_error_page($token_data->get_error_message());
            return;
        }
        
        // Verify shop ID matches token
        if ($token_data->shop_id != $shop_id) {
            $this->render_error_page(__('Ugyldigt butik ID', 'centershop_txtdomain'));
            return;
        }
        
        // Get shop details
        $shop = get_post($shop_id);
        if (!$shop) {
            $this->render_error_page(__('Butik ikke fundet', 'centershop_txtdomain'));
            return;
        }
        
        // Get Facebook App credentials
        $app_id = get_option('centershop_fb_app_id', '');
        if (empty($app_id)) {
            $this->render_error_page(__('Facebook app ikke konfigureret. Kontakt administrator.', 'centershop_txtdomain'));
            return;
        }
        
        // Build OAuth URL
        $callback_url = home_url('/connect-facebook/callback');
        $state = base64_encode(json_encode(array(
            'shop_id' => $shop_id,
            'token' => $token
        )));
        
        $oauth_url = 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query(array(
            'client_id' => $app_id,
            'redirect_uri' => $callback_url,
            'state' => $state,
            'scope' => 'pages_show_list,pages_read_engagement,pages_read_user_content,instagram_basic,instagram_manage_insights,instagram_content_publish'
        ));
        
        // Load template
        $this->load_template('tenant-connect', array(
            'shop' => $shop,
            'oauth_url' => $oauth_url,
            'mall_name' => get_bloginfo('name')
        ));
    }
    
    /**
     * Handle OAuth callback
     */
    private function handle_oauth_callback() {
        // Check if this is a page selection form submission
        $request_method = isset( $_SERVER['REQUEST_METHOD'] )
            ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
            : '';

        if ( 'POST' === $request_method && isset( $_POST['selected_page'] ) ) {
            $this->handle_page_selection_submission();
            return;
        }
        
        // Check for errors
        if (isset($_GET['error'])) {
            $error_message = isset($_GET['error_description']) 
                ? sanitize_text_field($_GET['error_description']) 
                : __('Facebook godkendelse fejlede', 'centershop_txtdomain');
            $this->render_error_page($error_message, true);
            return;
        }
        
        // Get authorization code
        $code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';
        if (empty($code)) {
            $this->render_error_page(__('Ingen authorization code modtaget', 'centershop_txtdomain'));
            return;
        }
        
        // Decode state
        $state = isset($_GET['state']) ? json_decode(base64_decode($_GET['state']), true) : array();
        if (empty($state['shop_id']) || empty($state['token'])) {
            $this->render_error_page(__('Ugyldig state parameter', 'centershop_txtdomain'));
            return;
        }
        
        $shop_id = intval($state['shop_id']);
        $token = sanitize_text_field($state['token']);
        
        // Validate token again
        $token_data = $this->connections->validate_magic_token($token);
        if (is_wp_error($token_data)) {
            $this->render_error_page($token_data->get_error_message());
            return;
        }
        
        // Exchange code for access token
        $app_id = get_option('centershop_fb_app_id', '');
        $app_secret = get_option('centershop_fb_app_secret', '');
        $callback_url = home_url('/connect-facebook/callback');
        
        $token_url = 'https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query(array(
            'client_id' => $app_id,
            'client_secret' => $app_secret,
            'redirect_uri' => $callback_url,
            'code' => $code
        ));
        
        $response = wp_remote_get($token_url);
        if (is_wp_error($response)) {
            $this->render_error_page(__('Kunne ikke hente access token', 'centershop_txtdomain'));
            return;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($body['access_token'])) {
            $this->render_error_page(__('Ingen access token i respons', 'centershop_txtdomain'));
            return;
        }
        
        $short_lived_token = $body['access_token'];
        
        // Exchange for long-lived token
        $long_lived_result = $this->api->exchange_for_long_lived_token($short_lived_token);
        if (is_wp_error($long_lived_result)) {
            $this->render_error_page($long_lived_result->get_error_message());
            return;
        }
        
        // Get user's pages
        $pages_result = $this->api->get_user_pages($long_lived_result['access_token']);
        if (is_wp_error($pages_result)) {
            $this->render_error_page($pages_result->get_error_message());
            return;
        }
        
        // Collect all available accounts (Facebook Pages and Instagram Business Accounts)
        $available_accounts = array();
        
        foreach ($pages_result as $page) {
            // Add Facebook page
            $available_accounts[] = array(
                'id' => $page['id'],
                'name' => $page['name'],
                'access_token' => $page['access_token'],
                'type' => 'facebook'
            );
            
            // Check if this page has an Instagram Business Account
            if (isset($page['instagram_business_account']['id'])) {
                $ig_account_id = $page['instagram_business_account']['id'];
                $ig_info = $this->api->get_instagram_account($ig_account_id, $page['access_token']);
                
                if (!is_wp_error($ig_info)) {
                    $available_accounts[] = array(
                        'id' => $ig_account_id,
                        'name' => '@' . ($ig_info['username'] ?? $ig_info['name'] ?? 'Instagram'),
                        'access_token' => $page['access_token'],
                        'type' => 'instagram',
                        'username' => $ig_info['username'] ?? null
                    );
                } elseif (defined('WP_DEBUG') && WP_DEBUG) {
                    // Log Instagram account fetch failures for debugging
                    error_log(sprintf(
                        'CenterShop: Failed to fetch Instagram account %s: %s',
                        $ig_account_id,
                        $ig_info->get_error_message()
                    ));
                }
            }
        }
        
        if (empty($available_accounts)) {
            $this->render_error_page(__('Ingen Facebook sider eller Instagram konti fundet. Du skal være administrator af en Facebook Business side eller have en forbundet Instagram Business konto.', 'centershop_txtdomain'));
            return;
        }

        // Derive effective shop ID from validated token data to prevent tampering
        if (isset($token_data) && isset($token_data->shop_id)) {
            // If both client-provided and token-derived shop IDs exist, ensure they match
            if (isset($shop_id) && (string) $shop_id !== (string) $token_data->shop_id) {
                $this->render_error_page(__('Ugyldig forespørgsel: butik-id matcher ikke godkendt token.', 'centershop_txtdomain'));
                return;
            }
            $effective_shop_id = (int) $token_data->shop_id;
        } else {
            // Fallback to existing shop_id if token data is unavailable
            $effective_shop_id = isset($shop_id) ? (int) $shop_id : 0;
        }
        
        // If only one account, auto-select it
        if (count($available_accounts) === 1) {
            $account = $available_accounts[0];
            $this->save_connection_and_show_success($effective_shop_id, $token, $account);
            return;
        }
        
        // Store accounts in transient for page selection form
        $transient_key = 'centershop_fb_pages_' . $effective_shop_id . '_' . hash('sha256', $token);
        set_transient($transient_key, $available_accounts, HOUR_IN_SECONDS);
        
        // Show page selection
        $this->load_template('tenant-page-selection', array(
            'shop_id' => $effective_shop_id,
            'token' => $token,
            'pages' => $available_accounts,
            'transient_key' => $transient_key
        ));
    }
    
    /**
     * Handle page selection form submission
     */
    private function handle_page_selection_submission() {
        $client_shop_id = isset($_POST['shop_id']) ? intval($_POST['shop_id']) : 0;
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $selected_index = isset($_POST['selected_page']) ? intval($_POST['selected_page']) : -1;
        $transient_key = isset($_POST['transient_key']) ? sanitize_text_field($_POST['transient_key']) : '';
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';
        
        // Verify nonce for CSRF protection
        if (!wp_verify_nonce($nonce, 'centershop_fb_page_selection_' . $token)) {
            $this->render_error_page(__('Ugyldig sikkerhedstoken. Prøv venligst igen.', 'centershop_txtdomain'));
            return;
        }
        
        // Validate token
        $token_data = $this->connections->validate_magic_token($token);
        if (is_wp_error($token_data)) {
            $this->render_error_page($token_data->get_error_message());
            return;
        }
        
        // Use shop_id from validated token, not from client POST data
        $effective_shop_id = (int) $token_data->shop_id;
        
        // Verify client-provided shop_id matches token if provided
        if ($client_shop_id && (string) $client_shop_id !== (string) $effective_shop_id) {
            $this->render_error_page(__('Ugyldig forespørgsel: butik-id matcher ikke godkendt token.', 'centershop_txtdomain'));
            return;
        }
        
        // Get pages from transient
        $pages = get_transient($transient_key);
        if ($pages === false || !isset($pages[$selected_index])) {
            $this->render_error_page(__('Session udløbet. Prøv venligst igen.', 'centershop_txtdomain'));
            return;
        }
        
        $selected_page = $pages[$selected_index];
        
        // Delete transient
        delete_transient($transient_key);
        
        // Save and show success using validated shop_id
        $this->save_connection_and_show_success($effective_shop_id, $token, $selected_page);
    }
    
    /**
     * Save connection and show success page
     */
    private function save_connection_and_show_success($shop_id, $token, $page_data) {
        // Determine connection type from page_data
        $connection_type = isset($page_data['type']) ? $page_data['type'] : 'facebook';
        
        // Save connection
        $result = $this->connections->save_page_connection($shop_id, array(
            'page_id' => $page_data['id'],
            'page_name' => $page_data['name'],
            'access_token' => $page_data['access_token'],
            'connection_type' => $connection_type
        ));
        
        if (is_wp_error($result)) {
            $this->render_error_page($result->get_error_message());
            return;
        }
        
        // Mark token as used
        $this->connections->mark_token_used($token);
        
        // Send notification to admin
        $this->send_admin_notification($shop_id, $page_data['name'], $connection_type);
        
        // Show success page
        $shop = get_post($shop_id);
        $this->load_template('tenant-success', array(
            'shop' => $shop,
            'page_name' => $page_data['name'],
            'connection_type' => $connection_type,
            'mall_name' => get_bloginfo('name')
        ));
    }
    
    /**
     * Send notification to admin
     */
    private function send_admin_notification($shop_id, $page_name, $connection_type = 'facebook') {
        $shop = get_post($shop_id);
        $admin_email = get_option('admin_email');
        
        $platform_name = $connection_type === 'instagram' ? 'Instagram' : 'Facebook';
        
        $subject = sprintf(
            __('[%s] Ny %s forbindelse', 'centershop_txtdomain'),
            get_bloginfo('name'),
            $platform_name
        );
        
        $message = sprintf(
            __("En butik har forbundet deres %s:\n\nButik: %s\n%s: %s\n\nOpslag vil nu blive importeret automatisk.", 'centershop_txtdomain'),
            $platform_name,
            $shop->post_title,
            $platform_name,
            $page_name
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Render error page
     */
    private function render_error_page($message, $show_retry = false) {
        $this->load_template('tenant-error', array(
            'error_message' => $message,
            'show_retry' => $show_retry,
            'mall_name' => get_bloginfo('name')
        ));
    }
    
    /**
     * Load template file
     */
    private function load_template($template_name, $args = array()) {
        $template_path = CENTERSHOP_PLUGIN_DIR . 'templates/' . $template_name . '.php';
        
        if (file_exists($template_path)) {
            // Pass variables to template scope explicitly
            $shop = isset($args['shop']) ? $args['shop'] : null;
            $oauth_url = isset($args['oauth_url']) ? $args['oauth_url'] : '';
            $mall_name = isset($args['mall_name']) ? $args['mall_name'] : '';
            $page_name = isset($args['page_name']) ? $args['page_name'] : '';
            $error_message = isset($args['error_message']) ? $args['error_message'] : '';
            $show_retry = isset($args['show_retry']) ? $args['show_retry'] : false;
            $shop_id = isset($args['shop_id']) ? $args['shop_id'] : 0;
            $token = isset($args['token']) ? $args['token'] : '';
            $pages = isset($args['pages']) ? $args['pages'] : array();
            $transient_key = isset($args['transient_key']) ? $args['transient_key'] : '';
            
            include $template_path;
        } else {
            // Fallback inline template
            $this->render_inline_template($template_name, $args);
        }
    }
    
    /**
     * Render inline template as fallback
     */
    private function render_inline_template($template_name, $args) {
        // Explicit variable assignments instead of extract()
        $shop = isset($args['shop']) ? $args['shop'] : null;
        $oauth_url = isset($args['oauth_url']) ? $args['oauth_url'] : '';
        $mall_name = isset($args['mall_name']) ? $args['mall_name'] : '';
        $page_name = isset($args['page_name']) ? $args['page_name'] : '';
        $error_message = isset($args['error_message']) ? $args['error_message'] : '';
        $show_retry = isset($args['show_retry']) ? $args['show_retry'] : false;
        
        get_header();
        
        switch ($template_name) {
            case 'tenant-connect':
                ?>
                <div class="centershop-tenant-connect" style="max-width: 600px; margin: 50px auto; padding: 30px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <h1><?php _e('Forbind din Facebook side', 'centershop_txtdomain'); ?></h1>
                    <p><?php printf(__('Hej %s! Klik nedenfor for at forbinde din Facebook Business side til %s hjemmeside.', 'centershop_txtdomain'), esc_html($shop->post_title), esc_html($mall_name)); ?></p>
                    <p><a href="<?php echo esc_url($oauth_url); ?>" class="button button-primary button-large"><?php _e('Forbind Facebook side', 'centershop_txtdomain'); ?></a></p>
                    <p><small><?php _e('Du kan afbryde forbindelsen når som helst fra dine Facebook indstillinger.', 'centershop_txtdomain'); ?></small></p>
                </div>
                <?php
                break;
            
            case 'tenant-success':
                ?>
                <div class="centershop-tenant-success" style="max-width: 600px; margin: 50px auto; padding: 30px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <h1><?php _e('✓ Forbundet!', 'centershop_txtdomain'); ?></h1>
                    <p><?php printf(__('Din Facebook side "%s" er nu forbundet til %s.', 'centershop_txtdomain'), esc_html($page_name), esc_html($mall_name)); ?></p>
                    <p><?php _e('Dine opslag vil automatisk blive vist på hjemmesiden.', 'centershop_txtdomain'); ?></p>
                    <p><a href="<?php echo home_url(); ?>" class="button button-primary"><?php _e('Gå til hjemmesiden', 'centershop_txtdomain'); ?></a></p>
                </div>
                <?php
                break;
            
            case 'tenant-error':
                ?>
                <div class="centershop-tenant-error" style="max-width: 600px; margin: 50px auto; padding: 30px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <h1><?php _e('Fejl', 'centershop_txtdomain'); ?></h1>
                    <p style="color: #d63638;"><?php echo esc_html($error_message); ?></p>
                    <?php if ($show_retry): ?>
                        <p><a href="<?php echo home_url('/connect-facebook'); ?>" class="button"><?php _e('Prøv igen', 'centershop_txtdomain'); ?></a></p>
                    <?php endif; ?>
                    <p><a href="<?php echo home_url(); ?>"><?php _e('Gå til hjemmesiden', 'centershop_txtdomain'); ?></a></p>
                </div>
                <?php
                break;
        }
        
        get_footer();
    }
}
