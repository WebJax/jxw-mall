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
    const TOKEN_REFRESH_HOOK = 'centershop_fb_token_refresh';
    
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
        // Register cron hooks
        add_action(self::CRON_HOOK, array($this, 'run_daily_import'));
        add_action(self::TOKEN_REFRESH_HOOK, array($this, 'run_token_refresh'));
        
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
        $schedules['centershop_weekly'] = array(
            'interval' => WEEK_IN_SECONDS,
            'display' => __('Ugentligt (CenterShop)', 'centershop_txtdomain')
        );
        return $schedules;
    }
    
    /**
     * Schedule cron job
     */
    public static function schedule() {
        // Schedule daily import
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            // Schedule for 3 AM
            $timestamp = strtotime('tomorrow 03:00:00');
            wp_schedule_event($timestamp, 'centershop_daily', self::CRON_HOOK);
        }
        
        // Schedule weekly token refresh
        if (!wp_next_scheduled(self::TOKEN_REFRESH_HOOK)) {
            // Schedule for 2 AM on Sundays
            $now = time();
            if (date('w', $now) === '0') { // Today is Sunday
                $timestamp = strtotime('today 02:00:00');
                if ($timestamp <= $now) {
                    // If 2 AM today has already passed, schedule for next Sunday
                    $timestamp = strtotime('next Sunday 02:00:00');
                }
            } else {
                // Next upcoming Sunday
                $timestamp = strtotime('next Sunday 02:00:00');
            }
            wp_schedule_event($timestamp, 'centershop_weekly', self::TOKEN_REFRESH_HOOK);
        }
    }
    
    /**
     * Unschedule cron job
     */
    public static function unschedule() {
        // Unschedule daily import
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
        
        // Unschedule token refresh
        $timestamp = wp_next_scheduled(self::TOKEN_REFRESH_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::TOKEN_REFRESH_HOOK);
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
     * Run token refresh
     */
    public function run_token_refresh() {
        $connections = CenterShop_FB_Connections::get_instance();
        
        // Get connections expiring in 7 days
        $expiring = $connections->get_expiring_connections(7);
        
        if (empty($expiring)) {
            return;
        }
        
        $refreshed = 0;
        $failed = array();
        
        foreach ($expiring as $connection) {
            $result = $connections->refresh_token($connection->id);
            
            if (is_wp_error($result)) {
                $failed[] = array(
                    'shop_id' => $connection->shop_id,
                    'page_name' => $connection->fb_page_name,
                    'error' => $result->get_error_message(),
                    'expires' => $connection->token_expires
                );
            } else {
                $refreshed++;
            }
        }
        
        // Send email notification if there are failures
        if (!empty($failed)) {
            $this->send_token_refresh_failure_email($failed);
        }
        
        // Log results
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'CenterShop Token Refresh: Refreshed %d, Failed %d',
                $refreshed,
                count($failed)
            ));
        }
    }
    
    /**
     * Send token refresh failure notification
     */
    private function send_token_refresh_failure_email($failed_connections) {
        $admin_email = get_option('admin_email');
        
        $subject = sprintf(
            __('[%s] Facebook/Instagram token fornyelse fejlede', 'centershop_txtdomain'),
            get_bloginfo('name')
        );
        
        $message = __("Følgende forbindelser kunne ikke fornyes automatisk:\n\n", 'centershop_txtdomain');
        
        foreach ($failed_connections as $conn) {
            $shop = get_post($conn['shop_id']);
            $shop_name = $shop ? $shop->post_title : "Butik ID {$conn['shop_id']}";
            
            $message .= sprintf(
                "Butik: %s\nSide/Konto: %s\nUdløber: %s\nFejl: %s\n\n",
                $shop_name,
                $conn['page_name'],
                $conn['expires'],
                $conn['error']
            );
        }
        
        $message .= __("\nHandling påkrævet:\n", 'centershop_txtdomain');
        $message .= __("1. Kontroller at Facebook/Instagram app'en er aktiv\n", 'centershop_txtdomain');
        $message .= __("2. Generer nye magic links til berørte butikker\n", 'centershop_txtdomain');
        $message .= __("3. Send links til butikkerne for at genoprette forbindelsen\n", 'centershop_txtdomain');
        
        $sent = wp_mail($admin_email, $subject, $message);
        
        if (!$sent && defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'CenterShop Token Refresh: Failed to send failure notification email to %s with subject "%s".',
                $admin_email,
                $subject
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
