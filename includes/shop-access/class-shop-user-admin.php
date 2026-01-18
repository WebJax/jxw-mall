<?php
/**
 * Shop User Administration
 * 
 * Admin interface for managing shop users
 * - Opret butik-brugere
 * - Tilknyt brugere til butikker
 * - Administrer adgange
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_Shop_User_Admin {
    
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
        // Render shop access page
        add_action('centershop_render_shop_access', array($this, 'render_page'));
        
        // AJAX handlers
        add_action('wp_ajax_centershop_create_shop_user', array($this, 'ajax_create_user'));
        add_action('wp_ajax_centershop_update_shop_user', array($this, 'ajax_update_user'));
        add_action('wp_ajax_centershop_delete_shop_user', array($this, 'ajax_delete_user'));
        add_action('wp_ajax_centershop_send_credentials', array($this, 'ajax_send_credentials'));
        
        // Add shop column to users list
        add_filter('manage_users_columns', array($this, 'add_shop_column'));
        add_filter('manage_users_custom_column', array($this, 'show_shop_column'), 10, 3);
        
        // Add shop field to user profile
        add_action('show_user_profile', array($this, 'add_shop_field_to_profile'));
        add_action('edit_user_profile', array($this, 'add_shop_field_to_profile'));
        add_action('personal_options_update', array($this, 'save_shop_field'));
        add_action('edit_user_profile_update', array($this, 'save_shop_field'));
    }
    
    /**
     * Render shop access admin page
     */
    public function render_page() {
        // Handle form submissions
        $this->handle_form_submission();
        
        // Get all shops
        $shops = get_posts(array(
            'post_type' => 'butiksside',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish'
        ));
        
        // Get all shop users
        $shop_users = get_users(array(
            'role' => CenterShop_Shop_Roles::ROLE_NAME,
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));
        
        // Map users to shops
        $user_shop_map = array();
        foreach ($shop_users as $user) {
            $shop_id = get_user_meta($user->ID, 'centershop_shop_id', true);
            $user_shop_map[$shop_id] = $user;
        }
        
        ?>
        <div class="wrap centershop-shop-access">
            <h1><?php _e('Butik-adgange', 'centershop_txtdomain'); ?></h1>
            
            <p class="description">
                <?php _e('Her kan du oprette og administrere login til de enkelte butikker. Hver butik kan få sit eget login, så de kan uploade materiale til SoMe Planner og redigere deres butiksprofil.', 'centershop_txtdomain'); ?>
            </p>
            
            <!-- Ny bruger form -->
            <div class="centershop-card">
                <h2><?php _e('Opret ny butik-bruger', 'centershop_txtdomain'); ?></h2>
                <form method="post" class="centershop-form">
                    <?php wp_nonce_field('centershop_create_shop_user', 'centershop_shop_user_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="shop_id"><?php _e('Butik', 'centershop_txtdomain'); ?></label></th>
                            <td>
                                <select name="shop_id" id="shop_id" required>
                                    <option value=""><?php _e('Vælg butik...', 'centershop_txtdomain'); ?></option>
                                    <?php foreach ($shops as $shop): ?>
                                        <?php 
                                        $has_user = isset($user_shop_map[$shop->ID]);
                                        $disabled = $has_user ? 'disabled' : '';
                                        $suffix = $has_user ? ' (' . __('har allerede bruger', 'centershop_txtdomain') . ')' : '';
                                        ?>
                                        <option value="<?php echo $shop->ID; ?>" <?php echo $disabled; ?>>
                                            <?php echo esc_html($shop->post_title . $suffix); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="user_email"><?php _e('E-mail', 'centershop_txtdomain'); ?></label></th>
                            <td>
                                <input type="email" name="user_email" id="user_email" class="regular-text" required>
                                <p class="description"><?php _e('Bruges som brugernavn og til at sende login-oplysninger', 'centershop_txtdomain'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="send_email"><?php _e('Send velkomstmail', 'centershop_txtdomain'); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="send_email" id="send_email" value="1" checked>
                                    <?php _e('Send login-oplysninger til brugeren', 'centershop_txtdomain'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="create_shop_user" class="button button-primary" value="<?php _e('Opret bruger', 'centershop_txtdomain'); ?>">
                    </p>
                </form>
            </div>
            
            <!-- Eksisterende brugere -->
            <div class="centershop-card">
                <h2><?php _e('Eksisterende butik-brugere', 'centershop_txtdomain'); ?></h2>
                
                <?php if (empty($shop_users)): ?>
                    <p><?php _e('Ingen butik-brugere oprettet endnu.', 'centershop_txtdomain'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Butik', 'centershop_txtdomain'); ?></th>
                                <th><?php _e('Brugernavn', 'centershop_txtdomain'); ?></th>
                                <th><?php _e('E-mail', 'centershop_txtdomain'); ?></th>
                                <th><?php _e('Sidst logget ind', 'centershop_txtdomain'); ?></th>
                                <th><?php _e('Handlinger', 'centershop_txtdomain'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shop_users as $user): ?>
                                <?php 
                                $shop_id = get_user_meta($user->ID, 'centershop_shop_id', true);
                                $shop = $shop_id ? get_post($shop_id) : null;
                                $last_login = get_user_meta($user->ID, 'centershop_last_login', true);
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($shop): ?>
                                            <a href="<?php echo get_edit_post_link($shop_id); ?>">
                                                <?php echo esc_html($shop->post_title); ?>
                                            </a>
                                        <?php else: ?>
                                            <em><?php _e('Ikke tilknyttet', 'centershop_txtdomain'); ?></em>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($user->user_login); ?></td>
                                    <td><?php echo esc_html($user->user_email); ?></td>
                                    <td>
                                        <?php 
                                        if ($last_login) {
                                            echo human_time_diff($last_login) . ' ' . __('siden', 'centershop_txtdomain');
                                        } else {
                                            echo '<em>' . __('Aldrig', 'centershop_txtdomain') . '</em>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo get_edit_user_link($user->ID); ?>" class="button button-small">
                                            <?php _e('Rediger', 'centershop_txtdomain'); ?>
                                        </a>
                                        <button type="button" class="button button-small centershop-send-credentials" 
                                                data-user-id="<?php echo $user->ID; ?>">
                                            <?php _e('Send login', 'centershop_txtdomain'); ?>
                                        </button>
                                        <button type="button" class="button button-small button-link-delete centershop-delete-user" 
                                                data-user-id="<?php echo $user->ID; ?>"
                                                data-user-name="<?php echo esc_attr($user->display_name); ?>">
                                            <?php _e('Slet', 'centershop_txtdomain'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Butikker uden bruger -->
            <?php 
            $shops_without_user = array_filter($shops, function($shop) use ($user_shop_map) {
                return !isset($user_shop_map[$shop->ID]);
            });
            ?>
            
            <?php if (!empty($shops_without_user)): ?>
            <div class="centershop-card">
                <h2><?php _e('Butikker uden bruger', 'centershop_txtdomain'); ?></h2>
                <p class="description"><?php _e('Disse butikker har endnu ikke fået oprettet et login.', 'centershop_txtdomain'); ?></p>
                <ul>
                    <?php foreach ($shops_without_user as $shop): ?>
                        <li>
                            <strong><?php echo esc_html($shop->post_title); ?></strong>
                            <?php 
                            $email = get_post_meta($shop->ID, 'butik_payed_mail', true);
                            if ($email) {
                                echo ' - ' . esc_html($email);
                            }
                            ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Handle form submission
     */
    private function handle_form_submission() {
        if (!isset($_POST['create_shop_user'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['centershop_shop_user_nonce'], 'centershop_create_shop_user')) {
            wp_die(__('Sikkerhedsfejl', 'centershop_txtdomain'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Du har ikke tilladelse til dette', 'centershop_txtdomain'));
        }
        
        $shop_id = intval($_POST['shop_id']);
        $email = sanitize_email($_POST['user_email']);
        $send_email = isset($_POST['send_email']);
        
        if (!$shop_id || !$email) {
            add_settings_error('centershop', 'missing_data', __('Alle felter skal udfyldes', 'centershop_txtdomain'), 'error');
            return;
        }
        
        // Check if email already exists
        if (email_exists($email)) {
            add_settings_error('centershop', 'email_exists', __('E-mail adressen er allerede i brug', 'centershop_txtdomain'), 'error');
            return;
        }
        
        // Get shop info
        $shop = get_post($shop_id);
        if (!$shop) {
            add_settings_error('centershop', 'invalid_shop', __('Ugyldig butik', 'centershop_txtdomain'), 'error');
            return;
        }
        
        // Generate password
        $password = wp_generate_password(12, true, true);
        
        // Create user
        $user_id = wp_create_user($email, $password, $email);
        
        if (is_wp_error($user_id)) {
            add_settings_error('centershop', 'user_error', $user_id->get_error_message(), 'error');
            return;
        }
        
        // Set role
        $user = new WP_User($user_id);
        $user->set_role(CenterShop_Shop_Roles::ROLE_NAME);
        
        // Set display name
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $shop->post_title,
            'first_name' => $shop->post_title
        ));
        
        // Link to shop
        update_user_meta($user_id, 'centershop_shop_id', $shop_id);
        
        // Send email if requested
        if ($send_email) {
            $this->send_credentials_email($user_id, $password);
        }
        
        add_settings_error('centershop', 'user_created', 
            sprintf(__('Bruger oprettet for %s', 'centershop_txtdomain'), $shop->post_title), 
            'success'
        );
    }
    
    /**
     * Send credentials email
     */
    private function send_credentials_email($user_id, $password = null) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        $shop_id = get_user_meta($user_id, 'centershop_shop_id', true);
        $shop = $shop_id ? get_post($shop_id) : null;
        $shop_name = $shop ? $shop->post_title : __('din butik', 'centershop_txtdomain');
        
        // If no password provided, generate reset link
        if (!$password) {
            $reset_key = get_password_reset_key($user);
            $reset_url = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($user->user_login), 'login');
            
            $message = sprintf(
                __("Hej %s,\n\nDu kan nu logge ind på %s's SoMe Planner.\n\nBrugernavn: %s\n\nKlik her for at vælge din adgangskode:\n%s\n\nEfter login kan du:\n- Uploade billeder og video til centrets sociale medier\n- Redigere din butiksprofil\n\nMed venlig hilsen\n%s", 'centershop_txtdomain'),
                $shop_name,
                get_bloginfo('name'),
                $user->user_login,
                $reset_url,
                get_bloginfo('name')
            );
        } else {
            $message = sprintf(
                __("Hej %s,\n\nDer er oprettet et login til dig på %s's SoMe Planner.\n\nBrugernavn: %s\nAdgangskode: %s\n\nLog ind her: %s\n\nEfter login kan du:\n- Uploade billeder og video til centrets sociale medier\n- Redigere din butiksprofil\n\nMed venlig hilsen\n%s", 'centershop_txtdomain'),
                $shop_name,
                get_bloginfo('name'),
                $user->user_login,
                $password,
                admin_url(),
                get_bloginfo('name')
            );
        }
        
        $subject = sprintf(__('Dit login til %s SoMe Planner', 'centershop_txtdomain'), get_bloginfo('name'));
        
        return wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * AJAX: Create shop user
     */
    public function ajax_create_user() {
        check_ajax_referer('centershop_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Ingen adgang', 'centershop_txtdomain'));
        }
        
        // Similar to handle_form_submission
        wp_send_json_success();
    }
    
    /**
     * AJAX: Update shop user
     */
    public function ajax_update_user() {
        check_ajax_referer('centershop_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Ingen adgang', 'centershop_txtdomain'));
        }
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Delete shop user
     */
    public function ajax_delete_user() {
        check_ajax_referer('centershop_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Ingen adgang', 'centershop_txtdomain'));
        }
        
        $user_id = intval($_POST['user_id']);
        
        if (!$user_id) {
            wp_send_json_error(__('Ugyldig bruger', 'centershop_txtdomain'));
        }
        
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        
        if (wp_delete_user($user_id)) {
            wp_send_json_success(__('Bruger slettet', 'centershop_txtdomain'));
        } else {
            wp_send_json_error(__('Kunne ikke slette bruger', 'centershop_txtdomain'));
        }
    }
    
    /**
     * AJAX: Send credentials
     */
    public function ajax_send_credentials() {
        check_ajax_referer('centershop_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Ingen adgang', 'centershop_txtdomain'));
        }
        
        $user_id = intval($_POST['user_id']);
        
        if ($this->send_credentials_email($user_id)) {
            wp_send_json_success(__('Login-oplysninger sendt', 'centershop_txtdomain'));
        } else {
            wp_send_json_error(__('Kunne ikke sende e-mail', 'centershop_txtdomain'));
        }
    }
    
    /**
     * Add shop column to users list
     */
    public function add_shop_column($columns) {
        $columns['centershop_shop'] = __('Butik', 'centershop_txtdomain');
        return $columns;
    }
    
    /**
     * Show shop in column
     */
    public function show_shop_column($value, $column_name, $user_id) {
        if ($column_name !== 'centershop_shop') {
            return $value;
        }
        
        $shop_id = get_user_meta($user_id, 'centershop_shop_id', true);
        if (!$shop_id) {
            return '—';
        }
        
        $shop = get_post($shop_id);
        if (!$shop) {
            return '—';
        }
        
        return '<a href="' . get_edit_post_link($shop_id) . '">' . esc_html($shop->post_title) . '</a>';
    }
    
    /**
     * Add shop field to user profile
     */
    public function add_shop_field_to_profile($user) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $shop_id = get_user_meta($user->ID, 'centershop_shop_id', true);
        $shops = get_posts(array(
            'post_type' => 'butiksside',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        ?>
        <h3><?php _e('CenterShop', 'centershop_txtdomain'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="centershop_shop_id"><?php _e('Tilknyttet butik', 'centershop_txtdomain'); ?></label></th>
                <td>
                    <select name="centershop_shop_id" id="centershop_shop_id">
                        <option value=""><?php _e('Ingen', 'centershop_txtdomain'); ?></option>
                        <?php foreach ($shops as $shop): ?>
                            <option value="<?php echo $shop->ID; ?>" <?php selected($shop_id, $shop->ID); ?>>
                                <?php echo esc_html($shop->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save shop field from user profile
     */
    public function save_shop_field($user_id) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_POST['centershop_shop_id'])) {
            update_user_meta($user_id, 'centershop_shop_id', intval($_POST['centershop_shop_id']));
        }
    }
}
