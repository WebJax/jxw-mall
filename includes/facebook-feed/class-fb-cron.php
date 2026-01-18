<?php
/**
 * Facebook Feed - Cron Handler
 * 
 * Håndterer automatisk daglig import via WP-Cron
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_FB_Cron {
    
    /**
     * Cron hook name
     */
    const CRON_HOOK = 'centershop_fb_daily_import';
    
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
        // Register cron hook
        add_action(self::CRON_HOOK, array($this, 'run_daily_import'));
        
        // Add custom cron schedule
        add_filter('cron_schedules', array($this, 'add_cron_schedule'));
    }
    
    /**
     * Add custom cron schedule
     */
    public function add_cron_schedule($schedules) {
        $schedules['centershop_daily'] = array(
            'interval' => DAY_IN_SECONDS,
            'display' => __('Dagligt (CenterShop)', 'centershop_txtdomain')
        );
        return $schedules;
    }
    
    /**
     * Schedule cron job
     */
    public static function schedule() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            // Schedule for 3 AM
            $timestamp = strtotime('tomorrow 03:00:00');
            wp_schedule_event($timestamp, 'centershop_daily', self::CRON_HOOK);
        }
    }
    
    /**
     * Unschedule cron job
     */
    public static function unschedule() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }
    
    /**
     * Run daily import
     */
    public function run_daily_import() {
        // Check if auto-import is enabled
        if (!get_option('centershop_fb_auto_import', true)) {
            return;
        }
        
        $importer = CenterShop_FB_Importer::get_instance();
        
        // Import posts
        $results = $importer->import_all();
        
        // Cleanup old posts
        $deleted = $importer->cleanup_old_posts();
        
        // Log results
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'CenterShop FB Import: Imported %d, Skipped %d, Deleted %d',
                $results['imported'],
                $results['skipped'],
                $deleted
            ));
        }
    }
    
    /**
     * Get next scheduled run time
     */
    public static function get_next_run() {
        return wp_next_scheduled(self::CRON_HOOK);
    }
}
