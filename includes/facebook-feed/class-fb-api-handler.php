<?php
/**
 * Facebook Feed - API Handler
 * 
 * Kommunikation med Facebook Graph API
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_FB_API_Handler {
    
    /**
     * API version
     */
    const API_VERSION = 'v18.0';
    
    /**
     * Base URL
     */
    const BASE_URL = 'https://graph.facebook.com/';
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Access token
     */
    private $access_token;
    
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
        $this->access_token = get_option('centershop_fb_access_token', '');
    }
    
    /**
     * Make API request
     */
    public function request($endpoint, $params = array()) {
        // Only add default token if no token is explicitly provided
        if (!isset($params['access_token'])) {
            if (empty($this->access_token)) {
                return new WP_Error('no_token', __('Facebook access token ikke konfigureret', 'centershop_txtdomain'));
            }
            $params['access_token'] = $this->access_token;
        }
        
        $url = self::BASE_URL . self::API_VERSION . '/' . $endpoint;
        $url = add_query_arg($params, $url);
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return new WP_Error(
                'fb_api_error',
                $data['error']['message'] ?? __('Ukendt Facebook API fejl', 'centershop_txtdomain'),
                $data['error']
            );
        }
        
        return $data;
    }
    
    /**
     * Get page posts
     */
    public function get_page_posts($page_id, $limit = 10, $since = null, $page_token = null) {
        // Use page-specific token if provided, otherwise fall back to global token
        $token = $page_token ? $page_token : $this->access_token;
        
        if (empty($token)) {
            return new WP_Error('no_token', __('Ingen access token tilgængelig for denne side', 'centershop_txtdomain'));
        }
        
        $params = array(
            'fields' => 'id,message,created_time,permalink_url,full_picture,attachments{media_type,media,url}',
            'limit' => $limit,
            'access_token' => $token
        );
        
        if ($since) {
            $params['since'] = $since;
        }
        
        $result = $this->request($page_id . '/posts', $params);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return isset($result['data']) ? $result['data'] : array();
    }
    
    /**
     * Validate access token
     */
    public function validate_token() {
        $result = $this->request('me', array('fields' => 'id,name'));
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Get page info
     */
    public function get_page_info($page_id) {
        return $this->request($page_id, array(
            'fields' => 'id,name,picture,link'
        ));
    }
    
    /**
     * Set access token
     */
    public function set_access_token($token) {
        $this->access_token = $token;
        update_option('centershop_fb_access_token', $token);
    }
}
