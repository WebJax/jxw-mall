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
            'fields' => 'id,message,created_time,permalink_url,full_picture,attachments{media_type,media,url},likes.summary(true),comments.summary(true),shares',
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
    
    /**
     * Exchange short-lived token for long-lived token
     * 
     * This exchanges the short-lived user access token (1-2 hours) for a long-lived
     * user token (60 days). Page access tokens obtained from the long-lived user token
     * do not expire as long as the user remains an admin of the page.
     */
    public function exchange_for_long_lived_token($short_lived_token) {
        $app_id = get_option('centershop_fb_app_id', '');
        $app_secret = get_option('centershop_fb_app_secret', '');
        
        if (empty($app_id) || empty($app_secret)) {
            return new WP_Error('no_credentials', __('Facebook App ID og Secret ikke konfigureret', 'centershop_txtdomain'));
        }
        
        // Exchange for long-lived user token (valid for 60 days)
        // Page tokens obtained from this will not expire
        $params = array(
            'grant_type' => 'fb_exchange_token',
            'client_id' => $app_id,
            'client_secret' => $app_secret,
            'fb_exchange_token' => $short_lived_token
        );
        
        $result = $this->request('oauth/access_token', $params);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        if (!isset($result['access_token'])) {
            return new WP_Error('no_token', __('Ingen access token i respons', 'centershop_txtdomain'));
        }
        
        return array(
            'access_token' => $result['access_token'],
            'expires_in' => $result['expires_in'] ?? null
        );
    }
    
    /**
     * Get user's managed pages
     */
    public function get_user_pages($user_access_token) {
        $result = $this->request('me/accounts', array(
            'fields' => 'id,name,access_token',
            'access_token' => $user_access_token
        ));
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return isset($result['data']) ? $result['data'] : array();
    }
}
