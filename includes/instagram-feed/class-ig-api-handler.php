<?php
/**
 * Instagram Feed - API Handler
 * 
 * Kommunikation med Instagram Graph API
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_IG_API_Handler {
    
    /**
     * API version
     */
    const API_VERSION = 'v18.0';
    
    /**
     * Base URL
     */
    const BASE_URL = 'https://graph.instagram.com/';
    
    /**
     * Facebook Graph URL (for basic display API)
     */
    const FB_GRAPH_URL = 'https://graph.facebook.com/';
    
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
        $this->access_token = get_option('centershop_ig_access_token', '');
    }
    
    /**
     * Make API request
     */
    public function request($endpoint, $params = array(), $use_fb_graph = false) {
        // Only add default token if no token is explicitly provided
        if (!isset($params['access_token'])) {
            if (empty($this->access_token)) {
                return new WP_Error('no_token', __('Instagram access token ikke konfigureret', 'centershop_txtdomain'));
            }
            $params['access_token'] = $this->access_token;
        }
        
        $base_url = $use_fb_graph ? self::FB_GRAPH_URL : self::BASE_URL;
        $url = $base_url . $endpoint;
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
                'ig_api_error',
                $data['error']['message'] ?? __('Ukendt Instagram API fejl', 'centershop_txtdomain'),
                $data['error']
            );
        }
        
        return $data;
    }
    
    /**
     * Get user media
     */
    public function get_user_media($user_id = 'me', $limit = 10, $since = null, $user_token = null) {
        // Use user-specific token if provided, otherwise fall back to global token
        $token = $user_token ? $user_token : $this->access_token;
        
        if (empty($token)) {
            return new WP_Error('no_token', __('Ingen access token tilgængelig for denne bruger', 'centershop_txtdomain'));
        }
        
        $params = array(
            'fields' => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,children{media_type,media_url,thumbnail_url}',
            'limit' => $limit,
            'access_token' => $token
        );
        
        if ($since) {
            $params['since'] = $since;
        }
        
        $result = $this->request($user_id . '/media', $params);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return isset($result['data']) ? $result['data'] : array();
    }
    
    /**
     * Get media by ID
     */
    public function get_media($media_id, $token = null) {
        $params = array(
            'fields' => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp'
        );
        
        if ($token) {
            $params['access_token'] = $token;
        }
        
        return $this->request($media_id, $params);
    }
    
    /**
     * Validate access token
     */
    public function validate_token() {
        $result = $this->request('me', array('fields' => 'id,username'));
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Get user info
     */
    public function get_user_info($user_id = 'me') {
        return $this->request($user_id, array(
            'fields' => 'id,username,account_type,media_count'
        ));
    }
    
    /**
     * Refresh long-lived token
     */
    public function refresh_token($token = null) {
        $token_to_refresh = $token ? $token : $this->access_token;
        
        if (empty($token_to_refresh)) {
            return new WP_Error('no_token', __('Ingen token at forny', 'centershop_txtdomain'));
        }
        
        $result = $this->request('refresh_access_token', array(
            'grant_type' => 'ig_refresh_token',
            'access_token' => $token_to_refresh
        ));
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Update token if we refreshed the main token
        if (!$token && isset($result['access_token'])) {
            $this->set_access_token($result['access_token']);
        }
        
        return $result;
    }
    
    /**
     * Set access token
     */
    public function set_access_token($token) {
        $this->access_token = $token;
        update_option('centershop_ig_access_token', $token);
    }
}
