<?php
/**
 * Instagram Feed - Cron
 * 
 * Håndterer scheduled jobs for Instagram import
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_IG_Cron {
    
    /**
     * Cron hook name
     */
    const CRON_HOOK = 'centershop_ig_import_cron';
    
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
        // Schedule on init if not already scheduled
        add_action('init', array($this, 'maybe_schedule'));
    }
    
    /**
     * Maybe schedule cron
     */
    public function maybe_schedule() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            self::schedule();
        }
    }
    
    /**
     * Schedule import cron
     */
    public static function schedule() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(strtotime('03:30:00'), 'daily', self::CRON_HOOK);
        }
    }
    
    /**
     * Unschedule import cron
     */
    public static function unschedule() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }
    
    /**
     * Get next scheduled time
     */
    public static function get_next_scheduled() {
        return wp_next_scheduled(self::CRON_HOOK);
    }
}
