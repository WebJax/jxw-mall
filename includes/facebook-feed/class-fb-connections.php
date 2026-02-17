<?php
/**
 * Facebook Feed - Connections Handler
 * 
 * Manages tenant Facebook page connections
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_FB_Connections {
    
    /**
     * Table names (without prefix)
     */
    const CONNECTIONS_TABLE = 'centershop_fb_connections';
    const MAGIC_TOKENS_TABLE = 'centershop_fb_magic_tokens';
    
    /**
     * Database version
     */
    const DB_VERSION = '1.0';
    
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
        // Installation handled separately
    }
    
    /**
     * Get full table names with prefix
     */
    public static function get_connections_table() {
        global $wpdb;
        return $wpdb->prefix . self::CONNECTIONS_TABLE;
    }
    
    public static function get_magic_tokens_table() {
        global $wpdb;
        return $wpdb->prefix . self::MAGIC_TOKENS_TABLE;
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Connections table
        $connections_table = self::get_connections_table();
        $sql_connections = "CREATE TABLE $connections_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            shop_id bigint(20) UNSIGNED NOT NULL,
            fb_page_id varchar(100) NOT NULL,
            fb_page_name varchar(255) DEFAULT NULL,
            page_access_token text NOT NULL,
            token_expires datetime DEFAULT NULL,
            connected_date datetime DEFAULT CURRENT_TIMESTAMP,
            last_sync datetime DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            connection_type varchar(20) DEFAULT 'facebook',
            PRIMARY KEY (id),
            UNIQUE KEY shop_page (shop_id, fb_page_id),
            KEY shop_id (shop_id),
            KEY fb_page_id (fb_page_id),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Magic tokens table
        $magic_tokens_table = self::get_magic_tokens_table();
        $sql_magic_tokens = "CREATE TABLE $magic_tokens_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            shop_id bigint(20) UNSIGNED NOT NULL,
            token varchar(64) NOT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            expires_date datetime NOT NULL,
            used tinyint(1) DEFAULT 0,
            used_date datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY shop_id (shop_id),
            KEY expires_date (expires_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_connections);
        dbDelta($sql_magic_tokens);
        
        update_option('centershop_fb_connections_db_version', self::DB_VERSION);
    }
    
    /**
     * Create magic token for a shop
     */
    public function create_magic_token($shop_id, $user_id = null) {
        global $wpdb;
        
        if (!$shop_id || !get_post($shop_id)) {
            return new WP_Error('invalid_shop', __('Ugyldig butik ID', 'centershop_txtdomain'));
        }
        
        // Check permissions
        if ($user_id !== null && !$this->user_can_manage_shop($shop_id, $user_id)) {
            return new WP_Error('permission_denied', __('Du har ikke tilladelse til at generere link for denne butik', 'centershop_txtdomain'));
        }
        
        // Generate cryptographically secure token
        $token = bin2hex(random_bytes(32)); // 64 character hex string
        
        // Set expiration to 7 days from now
        $expires_date = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $result = $wpdb->insert(
            self::get_magic_tokens_table(),
            array(
                'shop_id' => intval($shop_id),
                'token' => $token,
                'expires_date' => $expires_date,
                'used' => 0
            ),
            array('%d', '%s', '%s', '%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Kunne ikke oprette token', 'centershop_txtdomain'));
        }
        
        return array(
            'token' => $token,
            'expires_date' => $expires_date,
            'link' => $this->get_connection_url($shop_id, $token)
        );
    }
    
    /**
     * Get connection URL
     */
    public function get_connection_url($shop_id, $token) {
        return add_query_arg(
            array(
                'shop' => $shop_id,
                'token' => $token
            ),
            home_url('/connect-facebook')
        );
    }
    
    /**
     * Validate magic token
     */
    public function validate_magic_token($token) {
        global $wpdb;
        
        $table = self::get_magic_tokens_table();
        
        $token_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE token = %s",
            $token
        ));
        
        if (!$token_data) {
            return new WP_Error('invalid_token', __('Ugyldig token', 'centershop_txtdomain'));
        }
        
        // Check if already used
        if ($token_data->used) {
            return new WP_Error('token_used', __('Token er allerede brugt', 'centershop_txtdomain'));
        }
        
        // Check if expired
        if (strtotime($token_data->expires_date) < current_time('timestamp')) {
            return new WP_Error('token_expired', __('Token er udløbet. Kontakt center admin for et nyt link.', 'centershop_txtdomain'));
        }
        
        // Verify shop exists
        if (!get_post($token_data->shop_id)) {
            return new WP_Error('shop_not_found', __('Butik ikke fundet', 'centershop_txtdomain'));
        }
        
        return $token_data;
    }
    
    /**
     * Mark token as used
     */
    public function mark_token_used($token) {
        global $wpdb;
        
        return $wpdb->update(
            self::get_magic_tokens_table(),
            array(
                'used' => 1,
                'used_date' => current_time('mysql')
            ),
            array('token' => $token),
            array('%d', '%s'),
            array('%s')
        );
    }
    
    /**
     * Save page connection
     */
    public function save_page_connection($shop_id, $page_data) {
        global $wpdb;
        
        if (!isset($page_data['page_id']) || !isset($page_data['access_token'])) {
            return new WP_Error('invalid_data', __('Manglende page data', 'centershop_txtdomain'));
        }
        
        $data = array(
            'shop_id' => intval($shop_id),
            'fb_page_id' => sanitize_text_field($page_data['page_id']),
            'fb_page_name' => isset($page_data['page_name']) ? sanitize_text_field($page_data['page_name']) : null,
            'page_access_token' => sanitize_text_field($page_data['access_token']),
            'token_expires' => isset($page_data['token_expires']) ? $page_data['token_expires'] : null,
            'connected_date' => current_time('mysql'),
            'is_active' => 1,
            'connection_type' => isset($page_data['connection_type']) ? sanitize_text_field($page_data['connection_type']) : 'facebook'
        );
        
        // Check if connection already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . self::get_connections_table() . " WHERE shop_id = %d AND fb_page_id = %s",
            $shop_id,
            $page_data['page_id']
        ));
        
        if ($existing) {
            // Update existing connection
            $result = $wpdb->update(
                self::get_connections_table(),
                $data,
                array('id' => $existing),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s'),
                array('%d')
            );
        } else {
            // Insert new connection
            $result = $wpdb->insert(
                self::get_connections_table(),
                $data,
                array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
            );
        }
        
        if ($result === false) {
            return new WP_Error('db_error', __('Kunne ikke gemme forbindelse', 'centershop_txtdomain'));
        }
        
        return $existing ? intval($existing) : $wpdb->insert_id;
    }
    
    /**
     * Get shop connections
     */
    public function get_shop_connections($shop_id, $active_only = true) {
        global $wpdb;
        
        $table = self::get_connections_table();
        
        if ($active_only) {
            $sql = $wpdb->prepare(
                "SELECT * FROM $table WHERE shop_id = %d AND is_active = 1 ORDER BY connected_date DESC",
                $shop_id
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM $table WHERE shop_id = %d ORDER BY connected_date DESC",
                $shop_id
            );
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get all active connections for import
     */
    public function get_all_active_connections() {
        global $wpdb;
        
        $table = self::get_connections_table();
        
        return $wpdb->get_results(
            "SELECT * FROM $table WHERE is_active = 1 ORDER BY shop_id, connected_date DESC"
        );
    }
    
    /**
     * Disconnect page
     */
    public function disconnect_page($connection_id) {
        global $wpdb;
        
        return $wpdb->update(
            self::get_connections_table(),
            array('is_active' => 0),
            array('id' => $connection_id),
            array('%d'),
            array('%d')
        );
    }
    
    /**
     * Delete connection permanently
     */
    public function delete_connection($connection_id) {
        global $wpdb;
        
        return $wpdb->delete(
            self::get_connections_table(),
            array('id' => $connection_id),
            array('%d')
        );
    }
    
    /**
     * Refresh token
     */
    public function refresh_token($connection_id) {
        global $wpdb;
        
        $connection = $this->get_connection($connection_id);
        if (!$connection) {
            return new WP_Error('connection_not_found', __('Forbindelse ikke fundet', 'centershop_txtdomain'));
        }
        
        // Get API handler
        $api = CenterShop_FB_API_Handler::get_instance();
        
        // Refresh the token
        $result = $api->refresh_access_token($connection->page_access_token);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Calculate new expiration date (60 days from now)
        $expires_date = isset($result['expires_in']) 
            ? date('Y-m-d H:i:s', time() + $result['expires_in'])
            : date('Y-m-d H:i:s', strtotime('+60 days'));
        
        // Update connection with new token and expiration
        $updated = $wpdb->update(
            self::get_connections_table(),
            array(
                'page_access_token' => $result['access_token'],
                'token_expires' => $expires_date
            ),
            array('id' => $connection_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($updated === false) {
            return new WP_Error('db_error', __('Kunne ikke opdatere token', 'centershop_txtdomain'));
        }
        
        return array(
            'success' => true,
            'expires' => $expires_date
        );
    }
    
    /**
     * Get connections expiring soon
     */
    public function get_expiring_connections($days = 7) {
        global $wpdb;
        
        $table = self::get_connections_table();
        $threshold_date = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE is_active = 1 
             AND token_expires IS NOT NULL 
             AND token_expires <= %s 
             AND token_expires > NOW()
             ORDER BY token_expires ASC",
            $threshold_date
        ));
    }
    
    /**
     * Get connection by ID
     */
    public function get_connection($connection_id) {
        global $wpdb;
        
        $table = self::get_connections_table();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $connection_id
        ));
    }
    
    /**
     * Update last sync time
     */
    public function update_last_sync($connection_id) {
        global $wpdb;
        
        return $wpdb->update(
            self::get_connections_table(),
            array('last_sync' => current_time('mysql')),
            array('id' => $connection_id),
            array('%s'),
            array('%d')
        );
    }
    
    /**
     * Clean up expired tokens
     */
    public function cleanup_expired_tokens() {
        global $wpdb;
        
        $table = self::get_magic_tokens_table();
        
        return $wpdb->query(
            "DELETE FROM $table WHERE expires_date < NOW() AND used = 0"
        );
    }
    
    /**
     * Get connection status for all shops
     */
    public function get_all_shops_status() {
        global $wpdb;
        
        // Get all shops (butiksside post type)
        $shops = get_posts(array(
            'post_type' => 'butiksside',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        $connections_table = self::get_connections_table();
        
        $status_list = array();
        
        foreach ($shops as $shop) {
            $connections = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $connections_table WHERE shop_id = %d AND is_active = 1",
                $shop->ID
            ));
            
            $status_list[] = array(
                'shop_id' => $shop->ID,
                'shop_name' => $shop->post_title,
                'connected' => !empty($connections),
                'connections' => $connections,
                'connection_count' => count($connections)
            );
        }
        
        return $status_list;
    }
    
    /**
     * Check if user can manage shop connections
     */
    public function user_can_manage_shop($shop_id, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // Admins can manage all shops
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // Check if user is shop manager for this shop
        if (CenterShop_Shop_Roles::is_shop_manager($user_id)) {
            $user_shop_id = CenterShop_Shop_Roles::get_user_shop_id($user_id);
            return $user_shop_id == $shop_id;
        }
        
        return false;
    }
    
    /**
     * Disconnect page with notifications
     */
    public function disconnect_page_with_notification($connection_id, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // Get connection details before disconnecting
        $connection = $this->get_connection($connection_id);
        if (!$connection) {
            return new WP_Error('connection_not_found', __('Forbindelse ikke fundet', 'centershop_txtdomain'));
        }
        
        // Check if user has permission
        if (!$this->user_can_manage_shop($connection->shop_id, $user_id)) {
            return new WP_Error('permission_denied', __('Du har ikke tilladelse til at fjerne denne forbindelse', 'centershop_txtdomain'));
        }
        
        // Disconnect
        $result = $this->disconnect_page($connection_id);
        
        if ($result === false) {
            return new WP_Error('disconnect_failed', __('Kunne ikke fjerne forbindelse', 'centershop_txtdomain'));
        }
        
        // Send notifications
        $this->send_disconnect_notifications($connection, $user_id);
        
        return array('success' => true);
    }
    
    /**
     * Send disconnect notifications
     */
    private function send_disconnect_notifications($connection, $disconnected_by_user_id) {
        $shop = get_post($connection->shop_id);
        $user = get_userdata($disconnected_by_user_id);
        $admin_email = get_option('admin_email');
        
        $platform_name = $connection->connection_type === 'instagram' ? 'Instagram' : 'Facebook';
        $disconnected_by = $user ? $user->display_name : 'Ukendt bruger';
        
        // Email to admin
        $admin_subject = sprintf(
            __('[%s] %s forbindelse fjernet', 'centershop_txtdomain'),
            get_bloginfo('name'),
            $platform_name
        );
        
        $admin_message = sprintf(
            __("En %s forbindelse er blevet fjernet:\n\nButik: %s\n%s side/konto: %s\nFjernet af: %s\nDato: %s\n", 'centershop_txtdomain'),
            $platform_name,
            $shop->post_title,
            $platform_name,
            $connection->fb_page_name,
            $disconnected_by,
            current_time('mysql')
        );
        
        wp_mail($admin_email, $admin_subject, $admin_message);
        
        // Email to shop owner (if email exists and not disconnected by admin)
        $shop_email = get_post_meta($connection->shop_id, 'butik_payed_mail', true);
        
        if ($shop_email && !user_can($disconnected_by_user_id, 'manage_options')) {
            $shop_subject = sprintf(
                __('[%s] Din %s forbindelse er fjernet', 'centershop_txtdomain'),
                get_bloginfo('name'),
                $platform_name
            );
            
            $shop_message = sprintf(
                __("Din %s forbindelse er blevet fjernet:\n\n%s side/konto: %s\nFjernet: %s\n\nKontakt center administratoren hvis du har spørgsmål.\n", 'centershop_txtdomain'),
                $platform_name,
                $platform_name,
                $connection->fb_page_name,
                current_time('mysql')
            );
            
            wp_mail($shop_email, $shop_subject, $shop_message);
        }
    }
}
