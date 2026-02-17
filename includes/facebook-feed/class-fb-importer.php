<?php
/**
 * Facebook Feed - Post Importer
 * 
 * Importerer opslag fra Facebook til WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_FB_Importer {
    
    /**
     * Instance
     */
    private static $instance = null;
    
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
     * Database handler
     */
    private $db;
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->api = CenterShop_FB_API_Handler::get_instance();
        $this->db = CenterShop_FB_Database::get_instance();
    }
    
    /**
     * Import posts from all configured pages
     */
    public function import_all() {
        $pages = $this->get_configured_pages();
        
        if (empty($pages)) {
            return array(
                'success' => false,
                'message' => __('Ingen Facebook sider konfigureret', 'centershop_txtdomain')
            );
        }
        
        $results = array(
            'success' => true,
            'imported' => 0,
            'skipped' => 0,
            'errors' => array(),
            'pages' => array()
        );
        
        foreach ($pages as $page) {
            $page_token = isset($page['page_token']) ? $page['page_token'] : null;
            $connection_id = isset($page['connection_id']) ? $page['connection_id'] : null;
            
            $page_result = $this->import_from_page($page['page_id'], $page['shop_id'] ?? null, $page_token, $connection_id);
            
            $results['pages'][$page['page_id']] = $page_result;
            
            if ($page_result['success']) {
                $results['imported'] += $page_result['imported'];
                $results['skipped'] += $page_result['skipped'];
            } else {
                $results['errors'][] = $page_result['message'];
            }
        }
        
        // Log import
        $this->log_import($results);
        
        return $results;
    }
    
    /**
     * Import posts from single page
     */
    public function import_from_page($page_id, $shop_id = null, $page_token = null, $connection_id = null) {
        $days_to_fetch = get_option('centershop_fb_days_to_fetch', 7);
        $since = date('Y-m-d', strtotime("-{$days_to_fetch} days"));
        
        // Use provided page token, or fall back to old page tokens config
        if (!$page_token) {
            $page_tokens = get_option('centershop_fb_page_tokens', array());
            $page_token = isset($page_tokens[$page_id]) ? $page_tokens[$page_id] : null;
        }
        
        $posts = $this->api->get_page_posts($page_id, 25, $since, $page_token);
        
        if (is_wp_error($posts)) {
            return array(
                'success' => false,
                'message' => $posts->get_error_message()
            );
        }
        
        $imported = 0;
        $skipped = 0;
        
        foreach ($posts as $fb_post) {
            // Skip posts without message
            if (empty($fb_post['message'])) {
                $skipped++;
                continue;
            }
            
            // Check if already exists
            if ($this->db->post_exists($fb_post['id'])) {
                $skipped++;
                continue;
            }
            
            // Insert into database
            $insert_id = $this->db->insert_post($fb_post, $page_id, $shop_id);
            
            if ($insert_id) {
                $imported++;
            }
        }
        
        // Update last sync time for connection
        if ($connection_id) {
            $connections_handler = CenterShop_FB_Connections::get_instance();
            $connections_handler->update_last_sync($connection_id);
        }
        
        return array(
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped
        );
    }
    
    /**
     * Get configured pages
     */
    private function get_configured_pages() {
        // First check for new connections system
        $connections_handler = CenterShop_FB_Connections::get_instance();
        $connections = $connections_handler->get_all_active_connections();
        
        $pages = array();
        
        // Convert connections to pages array format
        foreach ($connections as $connection) {
            $pages[] = array(
                'page_id' => $connection->fb_page_id,
                'shop_id' => $connection->shop_id,
                'page_token' => $connection->page_access_token,
                'connection_id' => $connection->id
            );
        }
        
        // Fallback to old config format if no connections
        if (empty($pages)) {
            $pages_config = get_option('centershop_fb_pages', '');
            
            if (empty($pages_config)) {
                return array();
            }
            
            $lines = explode("\n", $pages_config);
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Format: page_id or page_id:shop_id
                $parts = explode(':', $line);
                $pages[] = array(
                    'page_id' => trim($parts[0]),
                    'shop_id' => isset($parts[1]) ? intval(trim($parts[1])) : null
                );
            }
        }
        
        return $pages;
    }
    
    /**
     * Log import results
     */
    private function log_import($results) {
        $log = get_option('centershop_fb_import_log', array());
        
        array_unshift($log, array(
            'time' => current_time('mysql'),
            'results' => $results
        ));
        
        // Keep only last 50 entries
        $log = array_slice($log, 0, 50);
        
        update_option('centershop_fb_import_log', $log);
        update_option('centershop_fb_last_import', current_time('mysql'));
    }
    
    /**
     * Delete old posts
     */
    public function cleanup_old_posts() {
        $days_to_keep = get_option('centershop_fb_days_to_keep', 30);
        
        if ($days_to_keep <= 0) {
            return 0; // Don't delete anything
        }
        
        return $this->db->delete_old_posts($days_to_keep);
    }
}
