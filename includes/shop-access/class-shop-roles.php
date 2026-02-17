<?php
/**
 * Shop Roles & Permissions
 * 
 * Opretter og administrerer shop_manager rollen
 * og tilhørende capabilities
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_Shop_Roles {
    
    /**
     * Role name
     */
    const ROLE_NAME = 'shop_manager';
    
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
        // Filter capabilities
        add_filter('user_has_cap', array($this, 'filter_shop_capabilities'), 10, 4);
        add_filter('map_meta_cap', array($this, 'map_shop_meta_cap'), 10, 4);
        
        // Restrict media library
        add_filter('ajax_query_attachments_args', array($this, 'filter_media_library'));
        
        // Redirect after login
        add_filter('login_redirect', array($this, 'shop_login_redirect'), 10, 3);
        
        // Hide admin bar items for shop managers
        add_action('admin_bar_menu', array($this, 'modify_admin_bar'), 999);
        
        // Restrict admin menu
        add_action('admin_menu', array($this, 'restrict_admin_menu'), 999);
    }
    
    /**
     * Create shop_manager role on plugin activation
     */
    public static function create_role() {
        // Fjern eksisterende rolle først
        remove_role(self::ROLE_NAME);
        
        // Opret rolle med basis capabilities
        add_role(
            self::ROLE_NAME,
            __('Butiksejer', 'centershop_txtdomain'),
            array(
                'read' => true,
                'upload_files' => true,
                'edit_posts' => false, // Vi styrer dette via filter
                
                // Custom capabilities
                'centershop_view_dashboard' => true,
                'centershop_view_planner' => true,
                'centershop_upload_media' => true,
                'centershop_edit_own_shop' => true,
            )
        );
    }
    
    /**
     * Remove role on plugin deactivation
     */
    public static function remove_role() {
        remove_role(self::ROLE_NAME);
    }
    
    /**
     * Filter capabilities for shop_manager
     */
    public function filter_shop_capabilities($allcaps, $caps, $args, $user) {
        // Kun for shop_manager
        if (!in_array(self::ROLE_NAME, (array) $user->roles)) {
            return $allcaps;
        }
        
        // Hent butik tilknyttet brugeren
        $shop_id = get_user_meta($user->ID, 'centershop_shop_id', true);
        
        // Tillad redigering af egen butik
        if (isset($args[0]) && $args[0] === 'edit_post' && isset($args[2])) {
            $post_id = $args[2];
            $post = get_post($post_id);
            
            if ($post && $post->post_type === 'butiksside' && $post_id == $shop_id) {
                $allcaps['edit_post'] = true;
                $allcaps['edit_posts'] = true;
                $allcaps['edit_published_posts'] = true;
            }
            
            // Tillad redigering af egne SoMe posts
            if ($post && $post->post_type === 'centershop_post') {
                $post_shop_id = get_post_meta($post_id, '_centershop_shop_id', true);
                if ($post_shop_id == $shop_id) {
                    $allcaps['edit_post'] = true;
                    $allcaps['edit_posts'] = true;
                }
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Map meta capabilities for shop_manager
     */
    public function map_shop_meta_cap($caps, $cap, $user_id, $args) {
        $user = get_userdata($user_id);
        
        if (!$user || !in_array(self::ROLE_NAME, (array) $user->roles)) {
            return $caps;
        }
        
        $shop_id = get_user_meta($user_id, 'centershop_shop_id', true);
        
        // Handle edit_post capability
        if ($cap === 'edit_post' && !empty($args[0])) {
            $post = get_post($args[0]);
            
            if ($post) {
                // Egen butik
                if ($post->post_type === 'butiksside' && $post->ID == $shop_id) {
                    return array('exist'); // Grant capability
                }
                
                // Egen SoMe post
                if ($post->post_type === 'centershop_post') {
                    $post_shop_id = get_post_meta($post->ID, '_centershop_shop_id', true);
                    if ($post_shop_id == $shop_id) {
                        return array('exist');
                    }
                }
            }
        }
        
        return $caps;
    }
    
    /**
     * Filter media library to only show own uploads
     */
    public function filter_media_library($query) {
        $user = wp_get_current_user();
        
        if (in_array(self::ROLE_NAME, (array) $user->roles)) {
            $query['author'] = $user->ID;
        }
        
        return $query;
    }
    
    /**
     * Redirect shop_manager to CenterShop dashboard after login
     */
    public function shop_login_redirect($redirect_to, $requested_redirect_to, $user) {
        if (!is_wp_error($user) && in_array(self::ROLE_NAME, (array) $user->roles)) {
            return admin_url('admin.php?page=centershop');
        }
        return $redirect_to;
    }
    
    /**
     * Modify admin bar for shop managers
     */
    public function modify_admin_bar($wp_admin_bar) {
        $user = wp_get_current_user();
        
        if (!in_array(self::ROLE_NAME, (array) $user->roles)) {
            return;
        }
        
        // Fjern unødvendige menu items
        $wp_admin_bar->remove_node('new-content');
        $wp_admin_bar->remove_node('comments');
        $wp_admin_bar->remove_node('wp-logo');
    }
    
    /**
     * Restrict admin menu for shop managers
     */
    public function restrict_admin_menu() {
        $user = wp_get_current_user();
        
        if (!in_array(self::ROLE_NAME, (array) $user->roles)) {
            return;
        }
        
        // Fjern standard WordPress menuer
        remove_menu_page('index.php'); // Dashboard
        remove_menu_page('edit.php'); // Posts
        remove_menu_page('edit-comments.php'); // Comments
        remove_menu_page('tools.php'); // Tools
        
        // Behold kun:
        // - CenterShop (vores menu)
        // - Profile
        // - Media (begrænset til egne uploads)
    }
    
    /**
     * Check if current user is shop manager
     */
    public static function is_shop_manager($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        $user = get_userdata($user_id);
        return $user && in_array(self::ROLE_NAME, (array) $user->roles);
    }
    
    /**
     * Get shop ID for user
     */
    public static function get_user_shop_id($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        return get_user_meta($user_id, 'centershop_shop_id', true);
    }
    
    /**
     * Check if user can access shop
     */
    public static function can_access_shop($shop_id, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // Admins kan tilgå alt
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // Shop managers kan kun tilgå egen butik
        if (self::is_shop_manager($user_id)) {
            return self::get_user_shop_id($user_id) == $shop_id;
        }
        
        return false;
    }
}
