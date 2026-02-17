<?php
/**
 * Facebook Feed - Settings Page
 * 
 * Admin indstillingsside for Facebook Feed
 */

if (!defined('ABSPATH')) {
    exit;
}

class CenterShop_FB_Settings {
    
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
        add_action('centershop_render_facebook_settings', array($this, 'render_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_centershop_fb_manual_import', array($this, 'ajax_manual_import'));
        add_action('wp_ajax_centershop_fb_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_centershop_fb_clear_log', array($this, 'ajax_clear_log'));
        add_action('wp_ajax_centershop_fb_get_user_pages', array($this, 'ajax_fb_get_user_pages'));
        add_action('wp_ajax_centershop_fb_exchange_token', array($this, 'ajax_fb_exchange_token'));
        add_action('wp_ajax_centershop_fb_save_pages', array($this, 'ajax_fb_save_pages'));
        
        // Tenant connection AJAX handlers
        add_action('wp_ajax_centershop_fb_generate_magic_link', array($this, 'ajax_generate_magic_link'));
        add_action('wp_ajax_centershop_fb_send_connection_email', array($this, 'ajax_send_connection_email'));
        add_action('wp_ajax_centershop_fb_disconnect_shop', array($this, 'ajax_disconnect_shop'));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // API settings
        register_setting('centershop_fb_settings', 'centershop_fb_app_id');
        register_setting('centershop_fb_settings', 'centershop_fb_app_secret');
        register_setting('centershop_fb_settings', 'centershop_fb_access_token');
        
        // Page settings
        register_setting('centershop_fb_settings', 'centershop_fb_pages');
        
        // Import settings
        register_setting('centershop_fb_settings', 'centershop_fb_days_to_fetch', array(
            'default' => 7,
            'sanitize_callback' => 'absint'
        ));
        register_setting('centershop_fb_settings', 'centershop_fb_days_to_keep', array(
            'default' => 30,
            'sanitize_callback' => 'absint'
        ));
        register_setting('centershop_fb_settings', 'centershop_fb_auto_import', array(
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Handle settings save
        if (isset($_POST['submit']) && check_admin_referer('centershop_fb_settings-options')) {
            $this->save_settings();
        }
        
        $app_id = get_option('centershop_fb_app_id', '');
        $app_secret = get_option('centershop_fb_app_secret', '');
        $access_token = get_option('centershop_fb_access_token', '');
        $pages = get_option('centershop_fb_pages', '');
        $days_to_fetch = get_option('centershop_fb_days_to_fetch', 7);
        $days_to_keep = get_option('centershop_fb_days_to_keep', 30);
        $auto_import = get_option('centershop_fb_auto_import', true);
        $last_import = get_option('centershop_fb_last_import', '');
        
        ?>
        <style>
        #centershop-fb-magic-link-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #centershop-fb-magic-link-modal > div {
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
        }
        </style>
        <div class="wrap centershop-fb-settings">
            <h1><?php _e('Facebook Feed Indstillinger', 'centershop_txtdomain'); ?></h1>
            
            <!-- Status section -->
            <div class="centershop-card">
                <h2><?php _e('Status', 'centershop_txtdomain'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Sidst importeret', 'centershop_txtdomain'); ?></th>
                        <td>
                            <?php 
                            if ($last_import) {
                                echo esc_html(date_i18n('j. F Y H:i', strtotime($last_import)));
                            } else {
                                echo '<em>' . __('Aldrig', 'centershop_txtdomain') . '</em>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Næste planlagte import', 'centershop_txtdomain'); ?></th>
                        <td>
                            <?php 
                            $next_run = CenterShop_FB_Cron::get_next_run();
                            if ($next_run && $auto_import) {
                                echo esc_html(date_i18n('j. F Y H:i', $next_run));
                            } else {
                                echo '<em>' . __('Ikke planlagt', 'centershop_txtdomain') . '</em>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Importerede opslag', 'centershop_txtdomain'); ?></th>
                        <td>
                            <?php 
                            $db = CenterShop_FB_Database::get_instance();
                            $count = $db->get_total_count();
                            echo $count;
                            ?>
                            <a href="<?php echo admin_url('admin.php?page=centershop-facebook-posts'); ?>" class="button button-small">
                                <?php _e('Se alle', 'centershop_txtdomain'); ?>
                            </a>
                        </td>
                    </tr>
                </table>
                
                <p>
                    <button type="button" class="button button-primary" id="centershop-fb-import-now">
                        <?php _e('Importer nu', 'centershop_txtdomain'); ?>
                    </button>
                    <button type="button" class="button" id="centershop-fb-test-connection">
                        <?php _e('Test forbindelse', 'centershop_txtdomain'); ?>
                    </button>
                    <span id="centershop-fb-status-message"></span>
                </p>
            </div>
            
            <!-- Tenant Connections section -->
            <div class="centershop-card">
                <h2><?php _e('Butiksforbindelser', 'centershop_txtdomain'); ?></h2>
                
                <p><?php _e('Send forbindelseslinks til butikkerne så de selv kan forbinde deres Facebook sider.', 'centershop_txtdomain'); ?></p>
                
                <?php 
                $connections_handler = CenterShop_FB_Connections::get_instance();
                $shops_status = $connections_handler->get_all_shops_status();
                ?>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Butik', 'centershop_txtdomain'); ?></th>
                            <th><?php _e('Status', 'centershop_txtdomain'); ?></th>
                            <th><?php _e('Facebook Side', 'centershop_txtdomain'); ?></th>
                            <th><?php _e('Sidst synkroniseret', 'centershop_txtdomain'); ?></th>
                            <th><?php _e('Handlinger', 'centershop_txtdomain'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($shops_status)): ?>
                            <tr>
                                <td colspan="5"><?php _e('Ingen butikker fundet', 'centershop_txtdomain'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($shops_status as $shop_status): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($shop_status['shop_name']); ?></strong></td>
                                    <td>
                                        <?php if ($shop_status['connected']): ?>
                                            <span style="color:#46b450;">● <?php _e('Forbundet', 'centershop_txtdomain'); ?></span>
                                        <?php else: ?>
                                            <span style="color:#dc3232;">○ <?php _e('Ikke forbundet', 'centershop_txtdomain'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($shop_status['connections'])) {
                                            foreach ($shop_status['connections'] as $conn) {
                                                echo esc_html($conn->fb_page_name ?: $conn->fb_page_id);
                                                if (count($shop_status['connections']) > 1) {
                                                    echo '<br>';
                                                }
                                            }
                                        } else {
                                            echo '<em>—</em>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($shop_status['connections'])) {
                                            foreach ($shop_status['connections'] as $conn) {
                                                if ($conn->last_sync) {
                                                    echo esc_html(date_i18n('j. M Y H:i', strtotime($conn->last_sync)));
                                                } else {
                                                    echo '<em>' . __('Aldrig', 'centershop_txtdomain') . '</em>';
                                                }
                                                if (count($shop_status['connections']) > 1) {
                                                    echo '<br>';
                                                }
                                            }
                                        } else {
                                            echo '<em>—</em>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($shop_status['connected']): ?>
                                            <?php foreach ($shop_status['connections'] as $conn): ?>
                                                <button type="button" class="button button-small centershop-fb-disconnect" 
                                                        data-connection-id="<?php echo esc_attr($conn->id); ?>"
                                                        data-shop-name="<?php echo esc_attr($shop_status['shop_name']); ?>">
                                                    <?php _e('Afbryd', 'centershop_txtdomain'); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <button type="button" class="button button-primary centershop-fb-generate-link" 
                                                    data-shop-id="<?php echo esc_attr($shop_status['shop_id']); ?>"
                                                    data-shop-name="<?php echo esc_attr($shop_status['shop_name']); ?>">
                                                <?php _e('Generer Link', 'centershop_txtdomain'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- Magic link modal -->
                <div id="centershop-fb-magic-link-modal" style="display:none;">
                    <div style="background:#fff;padding:20px;border-radius:8px;max-width:600px;margin:50px auto;">
                        <h3><?php _e('Forbindelses Link', 'centershop_txtdomain'); ?></h3>
                        <p id="centershop-fb-link-shop-name"></p>
                        <p>
                            <input type="text" id="centershop-fb-magic-link-input" readonly style="width:100%;padding:8px;font-family:monospace;" />
                        </p>
                        <p>
                            <button type="button" class="button button-primary" id="centershop-fb-copy-link">
                                <?php _e('Kopier Link', 'centershop_txtdomain'); ?>
                            </button>
                            <button type="button" class="button" id="centershop-fb-send-email">
                                <?php _e('Send Email', 'centershop_txtdomain'); ?>
                            </button>
                            <button type="button" class="button" id="centershop-fb-close-modal">
                                <?php _e('Luk', 'centershop_txtdomain'); ?>
                            </button>
                        </p>
                        <p class="description">
                            <?php _e('Linket udløber om 7 dage og kan kun bruges én gang.', 'centershop_txtdomain'); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('centershop_fb_settings-options'); ?>
                
                <!-- API credentials -->
                <div class="centershop-card">
                    <h2><?php _e('Facebook API Opsætning', 'centershop_txtdomain'); ?></h2>
                    
                    <div style="background:#e7f3ff;padding:15px;border-left:4px solid #2271b1;margin-bottom:20px;">
                        <h3 style="margin-top:0;color:#135e96;">� Hurtig Opsætning</h3>
                        <p><?php _e('Log ind med Facebook for automatisk at opsætte integration med dine sider.', 'centershop_txtdomain'); ?></p>
                    </div>
                    
                    <!-- Step 1: App Credentials -->
                    <div style="margin-bottom:25px;padding:15px;background:#f9f9f9;border:1px solid #ddd;border-radius:4px;">
                        <h4 style="margin:0 0 15px 0;">📱 Trin 1: Indtast App Credentials</h4>
                        <p style="margin:0 0 10px 0;font-size:13px;color:#666;">
                            Har du ikke en app endnu? 
                            <a href="https://developers.facebook.com/apps/create/" target="_blank" style="font-weight:bold;">
                                Opret en Facebook App her →
                            </a>
                            <br><small>Vælg "Business" type og find dine credentials under "Settings" → "Basic"</small>
                        </p>
                        
                        <table class="form-table" style="margin:10px 0 0 0;">
                            <tr>
                                <th style="width:120px;"><label for="centershop_fb_app_id">App ID</label></th>
                                <td>
                                    <input type="text" id="centershop_fb_app_id" name="centershop_fb_app_id" 
                                           value="<?php echo esc_attr($app_id); ?>" class="regular-text" 
                                           placeholder="123456789012345">
                                    <p class="description"><?php _e('Dit Facebook App ID (15 cifre)', 'centershop_txtdomain'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="centershop_fb_app_secret">App Secret</label></th>
                                <td>
                                    <input type="password" id="centershop_fb_app_secret" name="centershop_fb_app_secret" 
                                           value="<?php echo esc_attr($app_secret); ?>" class="regular-text"
                                           placeholder="••••••••••••••••">
                                    <p class="description"><?php _e('Din hemmelige App Secret (klik "Show" på Facebook)', 'centershop_txtdomain'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php if (!empty($app_id) && !empty($app_secret)): ?>
                            <div style="margin-top:15px;padding:10px;background:#d4edda;border:1px solid #c3e6cb;border-radius:3px;">
                                <span style="color:#155724;">✓ App credentials gemt</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Step 2: Facebook Login (only shown if app credentials are set) -->
                    <?php if (!empty($app_id) && !empty($app_secret)): ?>
                    <div style="margin-bottom:25px;padding:15px;background:#f9f9f9;border:1px solid #ddd;border-radius:4px;">
                        <h4 style="margin:0 0 15px 0;">🔐 Trin 2: Log ind med Facebook</h4>
                        
                        <!-- Configuration checklist -->
                        <div style="margin-bottom:15px;padding:12px;background:#fff3cd;border-left:4px solid #ffc107;">
                            <strong style="color:#856404;">⚙️ Før du logger ind, tjek at din Facebook App er konfigureret:</strong>
                            <ol style="margin:10px 0 5px 20px;color:#856404;line-height:1.8;">
                                <li>Gå til <a href="https://developers.facebook.com/apps/<?php echo esc_attr($app_id); ?>/settings/basic/" target="_blank" style="font-weight:bold;color:#856404;">din app's indstillinger</a></li>
                                <li>Under <strong>"Settings" → "Advanced"</strong>:</li>
                                <li style="list-style:none;margin-left:20px;">
                                    <ul style="margin:5px 0;">
                                        <li>Sæt <strong>"Login med Javascript SDK"</strong> til <strong>JA</strong></li>
                                        <li>Tilføj dit domæne under <strong>"App Domains"</strong>: <code><?php echo esc_html(parse_url(home_url(), PHP_URL_HOST)); ?></code></li>
                                    </ul>
                                </li>
                                <li>Under <strong>"Facebook Login" → "Settings"</strong>:</li>
                                <li style="list-style:none;margin-left:20px;">
                                    <ul style="margin:5px 0;">
                                        <li>Tilføj til <strong>"Valid OAuth Redirect URIs"</strong>: <code><?php echo esc_html(admin_url('admin.php?page=centershop-facebook')); ?></code></li>
                                    </ul>
                                </li>
                                <li>Gem ændringer i Facebook</li>
                            </ol>
                        </div>
                        
                        <p style="margin:0 0 15px 0;font-size:13px;color:#666;">
                            Klik på knappen nedenfor for at logge ind med din Facebook konto. 
                            Du vil automatisk få vist alle de sider du administrerer.
                        </p>
                        
                        <button type="button" class="button button-primary button-large" id="centershop-fb-login" 
                                style="height:40px;padding:0 20px;font-size:14px;">
                            <span class="dashicons dashicons-facebook" style="font-size:20px;margin-top:6px;"></span>
                            <?php _e('Log ind med Facebook', 'centershop_txtdomain'); ?>
                        </button>
                        
                        <div id="centershop-fb-login-status" style="margin-top:15px;"></div>
                    </div>
                    
                    <!-- Step 3: Page Selection (shown after login) -->
                    <div id="centershop-fb-page-selector" style="display:none;margin-bottom:25px;padding:15px;background:#f9f9f9;border:1px solid #ddd;border-radius:4px;">
                        <h4 style="margin:0 0 15px 0;">📄 Trin 3: Vælg Facebook Sider</h4>
                        <p style="margin:0 0 15px 0;font-size:13px;color:#666;">
                            Vælg hvilke sider der skal importere opslag fra. Sider koblet automatisk til butikker er markeret med 
                            <span style="color:#46b450;font-weight:bold;">✓</span>
                        </p>
                        
                        <div id="centershop-fb-pages-list" style="max-height:400px;overflow-y:auto;"></div>
                        
                        <div style="margin-top:15px;padding-top:15px;border-top:1px solid #ddd;">
                            <button type="button" class="button button-primary" id="centershop-fb-save-pages">
                                <?php _e('Gem valgte sider', 'centershop_txtdomain'); ?>
                            </button>
                            <span id="centershop-fb-save-status" style="margin-left:10px;"></span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="margin-bottom:20px;padding:15px;background:#fff3cd;border-left:4px solid #ffc107;">
                        <p style="margin:0;color:#856404;">
                            <strong>⚠ Næste trin:</strong> Gem siden efter at have indtastet App ID og App Secret, 
                            så kommer "Login med Facebook" knappen frem.
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Manual token entry (collapsible alternative) -->
                    <?php if (!empty($app_id) && !empty($app_secret)): ?>
                    <details style="margin-bottom:20px;">
                        <summary style="cursor:pointer;padding:10px;background:#f0f0f1;border:1px solid #ddd;border-radius:3px;">
                            <strong>🔧 Avanceret: Manuel Token Indtastning</strong>
                            <small style="color:#666;margin-left:10px;">(hvis automatisk login ikke virker)</small>
                        </summary>
                        <div style="padding:15px;border:1px solid #ddd;border-top:none;">
                            <table class="form-table">
                                <tr>
                                    <th style="width:120px;"><label for="centershop_fb_access_token">Access Token</label></th>
                                    <td>
                                        <textarea id="centershop_fb_access_token" name="centershop_fb_access_token" 
                                                  class="large-text" rows="3" 
                                                  placeholder="EAAxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"><?php echo esc_textarea($access_token); ?></textarea>
                                        <p class="description">
                                            <?php _e('Hent fra', 'centershop_txtdomain'); ?> 
                                            <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </details>
                    <?php endif; ?>
                </div>
                
                <!-- Configured Pages (read-only display) -->
                <?php if (!empty($pages)): ?>
                <div class="centershop-card">
                    <h2><?php _e('Konfigurerede Facebook Sider', 'centershop_txtdomain'); ?></h2>
                    
                    <?php 
                    $page_tokens = get_option('centershop_fb_page_tokens', array());
                    $has_tokens = !empty($page_tokens);
                    ?>
                    
                    <!-- Token Status Warning -->
                    <?php if (!$has_tokens): ?>
                    <div style="background:#ffebee;border-left:4px solid #f44336;padding:15px;margin-bottom:20px;">
                        <h3 style="margin:0 0 10px 0;color:#c62828;">⚠️ VIGTIGT: Mangler Page Tokens!</h3>
                        <p style="margin:0 0 10px 0;color:#c62828;">
                            <strong>Dit system har kun page IDs men ingen page tokens gemt.</strong><br>
                            Dette betyder at import vil fejle med "Invalid OAuth 2.0 Access Token".
                        </p>
                        <p style="margin:0;color:#d32f2f;">
                            <strong>👉 DU SKAL:</strong> Klik på <strong>"Login med Facebook"</strong> knappen ovenfor 
                            og gennemfør login-processen igen for at gemme page tokens!
                        </p>
                    </div>
                    <?php else: ?>
                    <div style="background:#d4edda;border-left:4px solid #28a745;padding:12px;margin-bottom:20px;">
                        <span style="color:#155724;">✓ Page tokens gemt korrekt - import klar til brug</span>
                    </div>
                    <?php endif; ?>
                    
                    <table class="widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Side ID', 'centershop_txtdomain'); ?></th>
                                <th><?php _e('Koblet til butik', 'centershop_txtdomain'); ?></th>
                                <th><?php _e('Token Status', 'centershop_txtdomain'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $page_lines = array_filter(array_map('trim', explode("\n", $pages)));
                            foreach ($page_lines as $line):
                                $parts = explode(':', $line);
                                $page_id = $parts[0];
                                $shop_id = isset($parts[1]) ? $parts[1] : null;
                                $shop_name = null;
                                
                                if ($shop_id) {
                                    $shop = get_post($shop_id);
                                    $shop_name = $shop ? $shop->post_title : __('Ukendt butik', 'centershop_txtdomain');
                                }
                                
                                $has_page_token = isset($page_tokens[$page_id]) && !empty($page_tokens[$page_id]);
                            ?>
                                <tr>
                                    <td><code><?php echo esc_html($page_id); ?></code></td>
                                    <td>
                                        <?php if ($shop_name): ?>
                                            <strong><?php echo esc_html($shop_name); ?></strong>
                                            <small style="color:#666;">(ID: <?php echo esc_html($shop_id); ?>)</small>
                                        <?php else: ?>
                                            <em style="color:#999;"><?php _e('Ingen kobling', 'centershop_txtdomain'); ?></em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($has_page_token): ?>
                                            <span style="color:#46b450;">✓ Token OK</span>
                                            <small style="color:#666;">(<?php echo strlen($page_tokens[$page_id]); ?> tegn)</small>
                                        <?php else: ?>
                                            <span style="color:#d63301;font-weight:bold;">✗ Mangler token</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p style="margin-top:15px;color:#666;font-size:13px;">
                        <?php _e('💡 For at ændre sider, brug "Login med Facebook" knappen ovenfor.', 'centershop_txtdomain'); ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <!-- Import settings -->
                <div class="centershop-card">
                    <h2><?php _e('Import indstillinger', 'centershop_txtdomain'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="centershop_fb_days_to_fetch"><?php _e('Hent opslag fra de sidste', 'centershop_txtdomain'); ?></label></th>
                            <td>
                                <input type="number" id="centershop_fb_days_to_fetch" name="centershop_fb_days_to_fetch" 
                                       value="<?php echo esc_attr($days_to_fetch); ?>" min="1" max="30" class="small-text">
                                <?php _e('dage', 'centershop_txtdomain'); ?>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="centershop_fb_days_to_keep"><?php _e('Behold opslag i', 'centershop_txtdomain'); ?></label></th>
                            <td>
                                <input type="number" id="centershop_fb_days_to_keep" name="centershop_fb_days_to_keep" 
                                       value="<?php echo esc_attr($days_to_keep); ?>" min="0" max="365" class="small-text">
                                <?php _e('dage (0 = behold alle)', 'centershop_txtdomain'); ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Automatisk import', 'centershop_txtdomain'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="centershop_fb_auto_import" value="1" <?php checked($auto_import); ?>>
                                    <?php _e('Importer automatisk hver nat kl. 03:00', 'centershop_txtdomain'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(); ?>
            </form>
            
            <!-- Import log -->
            <div class="centershop-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                    <h2 style="margin:0;"><?php _e('Import log', 'centershop_txtdomain'); ?></h2>
                    <button type="button" class="button" id="centershop-fb-clear-log">
                        <span class="dashicons dashicons-trash" style="font-size:16px;margin-top:3px;"></span>
                        <?php _e('Ryd log', 'centershop_txtdomain'); ?>
                    </button>
                </div>
                <?php $this->render_import_log(); ?>
            </div>
        </div>
        
        <!-- Facebook SDK -->
        <script>
        window.fbAsyncInit = function() {
            FB.init({
                appId: '<?php echo esc_js($app_id); ?>',
                cookie: true,
                xfbml: true,
                version: 'v24.0'
            });
        };

        (function(d, s, id){
            var js, fjs = d.getElementsByTagName(s)[0];
            if (d.getElementById(id)) {return;}
            js = d.createElement(s); js.id = id;
            js.src = "https://connect.facebook.net/da_DK/sdk.js";
            fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk'));
        </script>
        
        <script>
        jQuery(document).ready(function($) {
            var userAccessToken = null;
            var userPages = [];
            var selectedPages = [];
            
            // Facebook Login
            $('#centershop-fb-login').on('click', function() {
                var $btn = $(this);
                var $status = $('#centershop-fb-login-status');
                
                $btn.prop('disabled', true);
                $status.html('<span style="color:#666;">⏳ Åbner Facebook login...</span>');
                
                FB.login(function(response) {
                    if (response.authResponse) {
                        userAccessToken = response.authResponse.accessToken;
                        $status.html('<span style="color:#46b450;">✓ Logget ind! Henter dine sider...</span>');
                        
                        // Get user's pages
                        $.post(ajaxurl, {
                            action: 'centershop_fb_get_user_pages',
                            nonce: '<?php echo wp_create_nonce('centershop_fb_oauth'); ?>',
                            access_token: userAccessToken
                        }, function(result) {
                            if (result.success) {
                                userPages = result.data;
                                displayPages(userPages);
                                $status.html('<span style="color:#46b450;">✓ Fandt ' + userPages.length + ' side(r)</span>');
                                $('#centershop-fb-page-selector').slideDown();
                            } else {
                                $status.html('<span style="color:#d63301;">✗ Fejl: ' + result.data + '</span>');
                                $btn.prop('disabled', false);
                            }
                        });
                    } else {
                        $status.html('<span style="color:#d63301;">✗ Login annulleret</span>');
                        $btn.prop('disabled', false);
                    }
                }, {
                    scope: 'pages_show_list,pages_read_engagement',
                    return_scopes: true
                });
            });
            
            // Display pages for selection
            function displayPages(pages) {
                var html = '<div style="border:1px solid #ddd;border-radius:3px;background:#fff;">';
                
                pages.forEach(function(page, index) {
                    var hasMatch = page.matched_shop !== null;
                    var matchInfo = '';
                    
                    if (hasMatch) {
                        matchInfo = '<div style="margin-top:5px;padding:5px;background:#d4edda;border-radius:3px;font-size:12px;">' +
                                   '<span style="color:#155724;">✓ Automatisk koblet til: <strong>' + 
                                   escapeHtml(page.matched_shop.title) + '</strong></span>' +
                                   '</div>';
                    }
                    
                    html += '<div style="padding:15px;border-bottom:1px solid #ddd;">' +
                           '<label style="display:block;cursor:pointer;">' +
                           '<input type="checkbox" class="page-checkbox" data-index="' + index + '" ' + 
                           (hasMatch ? 'checked' : '') + ' style="margin-right:10px;">' +
                           '<strong style="font-size:14px;">' + escapeHtml(page.name) + '</strong>' +
                           '<br><code style="font-size:11px;color:#666;margin-left:25px;">ID: ' + 
                           escapeHtml(page.id) + '</code>' +
                           '</label>' +
                           matchInfo;
                    
                    // Manual shop selector (if not auto-matched)
                    if (!hasMatch) {
                        html += '<div style="margin:10px 0 0 25px;">' +
                               '<label style="font-size:12px;color:#666;">Vælg butik (valgfrit):</label>' +
                               '<select class="shop-selector" data-index="' + index + '" style="margin-left:10px;font-size:12px;">' +
                               '<option value="">-- Ingen kobling --</option>' +
                               <?php 
                               $shops = get_posts(array('post_type' => 'butiksside', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
                               foreach ($shops as $shop) {
                                   echo '"<option value=\"' . esc_js($shop->ID) . '\">' . esc_js($shop->post_title) . '</option>" +';
                               }
                               ?>
                               '</select>' +
                               '</div>';
                    }
                    
                    html += '</div>';
                });
                
                html += '</div>';
                $('#centershop-fb-pages-list').html(html);
                
                // Update selected pages when checkboxes change
                $('.page-checkbox').on('change', updateSelectedPages);
                $('.shop-selector').on('change', updateSelectedPages);
            }
            
            function updateSelectedPages() {
                selectedPages = [];
                $('.page-checkbox:checked').each(function() {
                    var index = $(this).data('index');
                    var page = userPages[index];
                    var shopSelector = $('.shop-selector[data-index="' + index + '"]');
                    var shopId = shopSelector.length ? shopSelector.val() : 
                                (page.matched_shop ? page.matched_shop.id : null);
                    
                    selectedPages.push({
                        page_id: page.id,
                        page_name: page.name,
                        page_token: page.access_token,
                        shop_id: shopId
                    });
                });
            }
            
            // Save selected pages
            $('#centershop-fb-save-pages').on('click', function() {
                updateSelectedPages();
                
                if (selectedPages.length === 0) {
                    alert('<?php _e('Vælg mindst én side', 'centershop_txtdomain'); ?>');
                    return;
                }
                
                var $btn = $(this);
                var $status = $('#centershop-fb-save-status');
                
                $btn.prop('disabled', true).text('<?php _e('Gemmer...', 'centershop_txtdomain'); ?>');
                $status.html('<span style="color:#666;">⏳ Udveksler tokens...</span>');
                
                // Exchange token for long-lived
                $.post(ajaxurl, {
                    action: 'centershop_fb_exchange_token',
                    nonce: '<?php echo wp_create_nonce('centershop_fb_oauth'); ?>',
                    access_token: userAccessToken
                }, function(tokenResult) {
                    var mainToken = tokenResult.success ? tokenResult.data.access_token : userAccessToken;
                    
                    $status.html('<span style="color:#666;">⏳ Gemmer sider...</span>');
                    
                    // Save pages
                    $.post(ajaxurl, {
                        action: 'centershop_fb_save_pages',
                        nonce: '<?php echo wp_create_nonce('centershop_fb_oauth'); ?>',
                        pages: JSON.stringify(selectedPages),
                        main_token: mainToken
                    }, function(saveResult) {
                        if (saveResult.success) {
                            $status.html('<span style="color:#46b450;">✓ ' + saveResult.data.message + '</span>');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            $status.html('<span style="color:#d63301;">✗ ' + saveResult.data + '</span>');
                            $btn.prop('disabled', false).text('<?php _e('Gem valgte sider', 'centershop_txtdomain'); ?>');
                        }
                    });
                });
            });
            
            function escapeHtml(text) {
                var map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, function(m) { return map[m]; });
            }
            
            // Clear log
            $('#centershop-fb-clear-log').on('click', function() {
                if (!confirm('<?php _e('Er du sikker på at du vil slette hele import loggen?', 'centershop_txtdomain'); ?>')) {
                    return;
                }
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php _e('Sletter...', 'centershop_txtdomain'); ?>');
                
                $.post(ajaxurl, {
                    action: 'centershop_fb_clear_log',
                    nonce: '<?php echo wp_create_nonce('centershop_fb_clear_log'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data);
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="font-size:16px;margin-top:3px;"></span> <?php _e('Ryd log', 'centershop_txtdomain'); ?>');
                    }
                });
            });
            
            // Manual import
            $('#centershop-fb-import-now').on('click', function() {
                var $btn = $(this);
                var $msg = $('#centershop-fb-status-message');
                
                $btn.prop('disabled', true);
                $msg.text('<?php _e('Importerer...', 'centershop_txtdomain'); ?>');
                
                $.post(ajaxurl, {
                    action: 'centershop_fb_manual_import',
                    nonce: '<?php echo wp_create_nonce('centershop_fb_import'); ?>'
                }, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $msg.html('<span style="color:green">' + response.data + '</span>');
                    } else {
                        $msg.html('<span style="color:red">' + response.data + '</span>');
                    }
                });
            });
            
            // Test connection
            $('#centershop-fb-test-connection').on('click', function() {
                var $btn = $(this);
                var $msg = $('#centershop-fb-status-message');
                
                $btn.prop('disabled', true);
                $msg.text('<?php _e('Tester...', 'centershop_txtdomain'); ?>');
                
                $.post(ajaxurl, {
                    action: 'centershop_fb_test_connection',
                    nonce: '<?php echo wp_create_nonce('centershop_fb_test'); ?>'
                }, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $msg.html('<span style="color:green">' + response.data + '</span>');
                    } else {
                        $msg.html('<span style="color:red">' + response.data + '</span>');
                    }
                });
            });
            
            // Tenant connection handlers
            var currentShopId = null;
            var currentMagicLink = null;
            
            // Generate magic link
            $('.centershop-fb-generate-link').on('click', function() {
                var $btn = $(this);
                var shopId = $btn.data('shop-id');
                var shopName = $btn.data('shop-name');
                
                $btn.prop('disabled', true).text('Genererer...');
                
                $.post(ajaxurl, {
                    action: 'centershop_fb_generate_magic_link',
                    nonce: '<?php echo wp_create_nonce('centershop_fb_nonce'); ?>',
                    shop_id: shopId
                }, function(response) {
                    $btn.prop('disabled', false).text('<?php _e('Generer Link', 'centershop_txtdomain'); ?>');
                    
                    if (response.success) {
                        currentShopId = shopId;
                        currentMagicLink = response.data.link;
                        $('#centershop-fb-link-shop-name').text('Link til: ' + shopName);
                        $('#centershop-fb-magic-link-input').val(response.data.link);
                        $('#centershop-fb-magic-link-modal').show();
                    } else {
                        alert('Fejl: ' + response.data.message);
                    }
                });
            });
            
            // Copy link to clipboard
            $('#centershop-fb-copy-link').on('click', function() {
                var $input = $('#centershop-fb-magic-link-input');
                $input.select();
                document.execCommand('copy');
                $(this).text('✓ Kopieret!');
                setTimeout(function() {
                    $('#centershop-fb-copy-link').text('<?php _e('Kopier Link', 'centershop_txtdomain'); ?>');
                }, 2000);
            });
            
            // Send email
            $('#centershop-fb-send-email').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Sender...');
                
                $.post(ajaxurl, {
                    action: 'centershop_fb_send_connection_email',
                    nonce: '<?php echo wp_create_nonce('centershop_fb_nonce'); ?>',
                    shop_id: currentShopId,
                    magic_link: currentMagicLink
                }, function(response) {
                    $btn.prop('disabled', false).text('<?php _e('Send Email', 'centershop_txtdomain'); ?>');
                    
                    if (response.success) {
                        alert('✓ ' + response.data.message);
                    } else {
                        alert('Fejl: ' + response.data.message);
                    }
                });
            });
            
            // Close modal
            $('#centershop-fb-close-modal').on('click', function() {
                $('#centershop-fb-magic-link-modal').hide();
            });
            
            // Disconnect shop
            $('.centershop-fb-disconnect').on('click', function() {
                var $btn = $(this);
                var connectionId = $btn.data('connection-id');
                var shopName = $btn.data('shop-name');
                
                if (!confirm('Er du sikker på at du vil afbryde forbindelsen for ' + shopName + '?')) {
                    return;
                }
                
                $btn.prop('disabled', true).text('Afbryder...');
                
                $.post(ajaxurl, {
                    action: 'centershop_fb_disconnect_shop',
                    nonce: '<?php echo wp_create_nonce('centershop_fb_nonce'); ?>',
                    connection_id: connectionId
                }, function(response) {
                    if (response.success) {
                        alert('✓ ' + response.data.message);
                        location.reload();
                    } else {
                        $btn.prop('disabled', false).text('<?php _e('Afbryd', 'centershop_txtdomain'); ?>');
                        alert('Fejl: ' + response.data.message);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Extract username/page slug from Facebook URL
     * Simple extraction without API calls or scraping
     */
    private function extract_username_from_url($url) {
        if (empty($url)) {
            return null;
        }
        
        // Remove trailing slash and clean up
        $url = rtrim($url, '/');
        
        // Pattern 1: Direct numeric ID (facebook.com/123456789012345)
        if (preg_match('/facebook\.com\/(\d{15,16})/', $url, $matches)) {
            return $matches[1];
        }
        
        // Pattern 2: profile.php?id=123456789012345
        if (preg_match('/profile\.php\?id=(\d{15,16})/', $url, $matches)) {
            return $matches[1];
        }
        
        // Pattern 3: pages/PageName/123456789012345
        if (preg_match('/pages\/[^\/]+\/(\d{15,16})/', $url, $matches)) {
            return $matches[1];
        }
        
        // Pattern 4: Username/vanity URL (facebook.com/username)
        if (preg_match('/facebook\.com\/([a-zA-Z0-9\.\_\-]+)/', $url, $matches)) {
            $username = $matches[1];
            
            // Skip common non-username paths
            $skip_paths = array('pages', 'profile.php', 'groups', 'events', 'photo', 'permalink', 'story.php', 'watch', 'share', 'sharer');
            if (!in_array($username, $skip_paths)) {
                return $username;
            }
        }
        
        return null;
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        if (isset($_POST['centershop_fb_app_id'])) {
            update_option('centershop_fb_app_id', sanitize_text_field($_POST['centershop_fb_app_id']));
        }
        if (isset($_POST['centershop_fb_app_secret'])) {
            update_option('centershop_fb_app_secret', sanitize_text_field($_POST['centershop_fb_app_secret']));
        }
        if (isset($_POST['centershop_fb_access_token'])) {
            update_option('centershop_fb_access_token', sanitize_text_field($_POST['centershop_fb_access_token']));
        }
        if (isset($_POST['centershop_fb_pages'])) {
            update_option('centershop_fb_pages', sanitize_textarea_field($_POST['centershop_fb_pages']));
        }
        if (isset($_POST['centershop_fb_days_to_fetch'])) {
            update_option('centershop_fb_days_to_fetch', absint($_POST['centershop_fb_days_to_fetch']));
        }
        if (isset($_POST['centershop_fb_days_to_keep'])) {
            update_option('centershop_fb_days_to_keep', absint($_POST['centershop_fb_days_to_keep']));
        }
        
        $auto_import = isset($_POST['centershop_fb_auto_import']);
        update_option('centershop_fb_auto_import', $auto_import);
        
        // Update cron schedule
        if ($auto_import) {
            CenterShop_FB_Cron::schedule();
        } else {
            CenterShop_FB_Cron::unschedule();
        }
        
        add_settings_error('centershop_fb', 'settings_saved', __('Indstillinger gemt', 'centershop_txtdomain'), 'success');
    }
    
    /**
     * Render import log
     */
    private function render_import_log() {
        $log = get_option('centershop_fb_import_log', array());
        
        if (empty($log)) {
            echo '<p>' . __('Ingen import log endnu.', 'centershop_txtdomain') . '</p>';
            return;
        }
        
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __('Tidspunkt', 'centershop_txtdomain') . '</th>';
        echo '<th>' . __('Importeret', 'centershop_txtdomain') . '</th>';
        echo '<th>' . __('Sprunget over', 'centershop_txtdomain') . '</th>';
        echo '<th>' . __('Status', 'centershop_txtdomain') . '</th>';
        echo '</tr></thead><tbody>';
        
        foreach (array_slice($log, 0, 10) as $entry) {
            echo '<tr>';
            echo '<td>' . esc_html(date_i18n('j. F Y H:i', strtotime($entry['time']))) . '</td>';
            echo '<td>' . esc_html($entry['results']['imported'] ?? 0) . '</td>';
            echo '<td>' . esc_html($entry['results']['skipped'] ?? 0) . '</td>';
            echo '<td>';
            
            if (!empty($entry['results']['errors'])) {
                $errors = (array) $entry['results']['errors'];
                $error_count = count($errors);
                
                // Show first error with expandable details
                $first_error = $errors[0];
                
                // Simplify common error messages
                if (strpos($first_error, 'pages_read_engagement') !== false) {
                    echo '<span style="color:#d63301;">⚠ Mangler permissions</span>';
                    echo '<details style="margin-top:5px;font-size:11px;">';
                    echo '<summary style="cursor:pointer;color:#666;">Se detaljer</summary>';
                    echo '<div style="margin-top:5px;padding:5px;background:#fff3cd;border-left:2px solid #ffc107;">';
                    echo '<strong>Problem:</strong> Access token mangler rettigheder.<br>';
                    echo '<strong>Løsning:</strong> Tjek at din token har <code>pages_read_engagement</code> permission til siderne.';
                    echo '</div>';
                    echo '</details>';
                } elseif (strpos($first_error, 'does not exist') !== false) {
                    echo '<span style="color:#d63301;">⚠ Forkert Side ID</span>';
                    echo '<details style="margin-top:5px;font-size:11px;">';
                    echo '<summary style="cursor:pointer;color:#666;">Se detaljer</summary>';
                    echo '<div style="margin-top:5px;padding:5px;background:#ffebee;border-left:2px solid:#f44336;">';
                    echo '<strong>Problem:</strong> Siden findes ikke eller forkert ID format.<br>';
                    echo '<strong>Løsning:</strong> Tjek at du bruger det korrekte 15-cifrede Page ID fra "about_profile_transparency".';
                    echo '</div>';
                    echo '</details>';
                } else {
                    echo '<span style="color:#d63301;">✗ Fejl (' . $error_count . ')</span>';
                    echo '<details style="margin-top:5px;font-size:11px;">';
                    echo '<summary style="cursor:pointer;color:#666;">Se fejl</summary>';
                    echo '<pre style="margin-top:5px;padding:5px;background:#f5f5f5;overflow-x:auto;max-height:100px;">';
                    echo esc_html(implode("\n\n", array_slice($errors, 0, 3)));
                    echo '</pre>';
                    echo '</details>';
                }
            } else {
                echo '<span style="color:#46b450;">✓ Success</span>';
            }
            
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    /**
     * AJAX: Manual import
     */
    public function ajax_manual_import() {
        check_ajax_referer('centershop_fb_import', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Ingen adgang', 'centershop_txtdomain'));
        }
        
        $importer = CenterShop_FB_Importer::get_instance();
        $results = $importer->import_all();
        
        if ($results['success']) {
            wp_send_json_success(sprintf(
                __('Importeret: %d, Sprunget over: %d', 'centershop_txtdomain'),
                $results['imported'],
                $results['skipped']
            ));
        } else {
            wp_send_json_error($results['message']);
        }
    }
    
    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('centershop_fb_test', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Ingen adgang', 'centershop_txtdomain'));
        }
        
        $api = CenterShop_FB_API_Handler::get_instance();
        $result = $api->validate_token();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(sprintf(
            __('Forbindelse OK! Logget ind som: %s', 'centershop_txtdomain'),
            $result['name'] ?? 'Unknown'
        ));
    }
    
    /**
     * AJAX: Clear import log
     */
    public function ajax_clear_log() {
        check_ajax_referer('centershop_fb_clear_log', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Ingen adgang', 'centershop_txtdomain'));
        }
        
        delete_option('centershop_fb_import_log');
        
        wp_send_json_success(__('Import log slettet', 'centershop_txtdomain'));
    }
    
    /**
     * AJAX: Get user's Facebook pages
     */
    public function ajax_fb_get_user_pages() {
        check_ajax_referer('centershop_fb_oauth', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Ingen adgang', 'centershop_txtdomain'));
        }
        
        $user_token = isset($_POST['access_token']) ? sanitize_text_field($_POST['access_token']) : '';
        
        if (empty($user_token)) {
            wp_send_json_error(__('Mangler access token', 'centershop_txtdomain'));
        }
        
        // Get user's pages with access tokens
        $response = wp_remote_get(
            'https://graph.facebook.com/v24.0/me/accounts',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $user_token
                )
            )
        );
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['data'])) {
            wp_send_json_error(__('Kunne ikke hente sider', 'centershop_txtdomain'));
        }
        
        // Get shops for matching
        $shops = get_posts(array(
            'post_type' => 'butiksside',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        $shop_urls = array();
        foreach ($shops as $shop) {
            $fb_url = get_post_meta($shop->ID, 'butik_payed_fb', true);
            if ($fb_url) {
                $shop_urls[$shop->ID] = array(
                    'url' => $fb_url,
                    'title' => $shop->post_title,
                    'username' => $this->extract_username_from_url($fb_url)
                );
            }
        }
        
        // Match pages with shops
        $pages = array();
        foreach ($data['data'] as $page) {
            $matched_shop = null;
            
            // Try to match by page ID or username
            foreach ($shop_urls as $shop_id => $shop_data) {
                if ($shop_data['username'] === $page['id'] || 
                    $shop_data['username'] === strtolower($page['name']) ||
                    strpos($shop_data['url'], $page['id']) !== false) {
                    $matched_shop = array(
                        'id' => $shop_id,
                        'title' => $shop_data['title']
                    );
                    break;
                }
            }
            
            $pages[] = array(
                'id' => $page['id'],
                'name' => $page['name'],
                'access_token' => $page['access_token'],
                'matched_shop' => $matched_shop
            );
        }
        
        wp_send_json_success($pages);
    }
    
    /**
     * AJAX: Exchange short-lived token for long-lived token
     */
    public function ajax_fb_exchange_token() {
        check_ajax_referer('centershop_fb_oauth', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Ingen adgang', 'centershop_txtdomain'));
        }
        
        $short_token = isset($_POST['access_token']) ? sanitize_text_field($_POST['access_token']) : '';
        $app_id = get_option('centershop_fb_app_id', '');
        $app_secret = get_option('centershop_fb_app_secret', '');
        
        if (empty($short_token) || empty($app_id) || empty($app_secret)) {
            wp_send_json_error(__('Mangler credentials', 'centershop_txtdomain'));
        }
        
        // Exchange for long-lived token
        $response = wp_remote_get(
            add_query_arg(array(
                'grant_type' => 'fb_exchange_token',
                'client_id' => $app_id,
                'client_secret' => $app_secret,
                'fb_exchange_token' => $short_token
            ), 'https://graph.facebook.com/v24.0/oauth/access_token')
        );
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['access_token'])) {
            wp_send_json_error(__('Kunne ikke udveksle token', 'centershop_txtdomain'));
        }
        
        wp_send_json_success(array(
            'access_token' => $data['access_token'],
            'expires_in' => isset($data['expires_in']) ? $data['expires_in'] : null
        ));
    }
    
    /**
     * AJAX: Save selected pages
     */
    public function ajax_fb_save_pages() {
        check_ajax_referer('centershop_fb_oauth', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Ingen adgang', 'centershop_txtdomain'));
        }
        
        $pages = isset($_POST['pages']) ? json_decode(stripslashes($_POST['pages']), true) : array();
        $main_token = isset($_POST['main_token']) ? sanitize_text_field($_POST['main_token']) : '';
        
        if (empty($pages)) {
            wp_send_json_error(__('Ingen sider valgt', 'centershop_txtdomain'));
        }
        
        // Build page list and tokens array
        $page_list = array();
        $page_tokens = array();
        
        foreach ($pages as $page) {
            $page_id = $page['page_id'];
            $shop_id = isset($page['shop_id']) && !empty($page['shop_id']) ? $page['shop_id'] : null;
            $page_token = isset($page['page_token']) ? $page['page_token'] : '';
            
            // Save page ID with optional shop ID
            if ($shop_id) {
                $page_list[] = $page_id . ':' . $shop_id;
            } else {
                $page_list[] = $page_id;
            }
            
            // Save page token separately
            if (!empty($page_token)) {
                $page_tokens[$page_id] = $page_token;
            }
        }
        
        // Save page list and tokens
        update_option('centershop_fb_pages', implode("\n", $page_list));
        update_option('centershop_fb_page_tokens', $page_tokens);
        
        // Save main token as backup
        if (!empty($main_token)) {
            update_option('centershop_fb_access_token', $main_token);
        }
        
        wp_send_json_success(array(
            'message' => sprintf(
                __('%d sider gemt', 'centershop_txtdomain'),
                count($page_list)
            )
        ));
    }
    
    /**
     * AJAX: Generate magic link for shop
     */
    public function ajax_generate_magic_link() {
        check_ajax_referer('centershop_fb_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Ingen tilladelse', 'centershop_txtdomain')));
        }
        
        $shop_id = isset($_POST['shop_id']) ? intval($_POST['shop_id']) : 0;
        
        if (!$shop_id) {
            wp_send_json_error(array('message' => __('Mangler butik ID', 'centershop_txtdomain')));
        }
        
        $connections = CenterShop_FB_Connections::get_instance();
        $result = $connections->create_magic_token($shop_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'link' => $result['link'],
            'token' => $result['token'],
            'expires' => date_i18n('j. F Y H:i', strtotime($result['expires_date']))
        ));
    }
    
    /**
     * AJAX: Send connection email to shop
     */
    public function ajax_send_connection_email() {
        check_ajax_referer('centershop_fb_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Ingen tilladelse', 'centershop_txtdomain')));
        }
        
        $shop_id = isset($_POST['shop_id']) ? intval($_POST['shop_id']) : 0;
        $magic_link = isset($_POST['magic_link']) ? esc_url_raw($_POST['magic_link']) : '';
        
        if (!$shop_id || !$magic_link) {
            wp_send_json_error(array('message' => __('Mangler data', 'centershop_txtdomain')));
        }
        
        $shop = get_post($shop_id);
        if (!$shop) {
            wp_send_json_error(array('message' => __('Butik ikke fundet', 'centershop_txtdomain')));
        }
        
        // Get shop email from custom field
        $shop_email = get_post_meta($shop_id, 'butik_payed_mail', true);
        
        if (empty($shop_email)) {
            wp_send_json_error(array('message' => __('Ingen email adresse for denne butik', 'centershop_txtdomain')));
        }
        
        $mall_name = get_bloginfo('name');
        $shop_name = $shop->post_title;
        
        $subject = sprintf(__('Forbind din Facebook til %s hjemmeside', 'centershop_txtdomain'), $mall_name);
        
        $message = sprintf(
            __("Hej %s,\n\nVi vil gerne fremvise dine Facebook og Instagram opslag på vores hjemmeside for at hjælpe med at promovere din butik!\n\nKlik på linket nedenfor for at forbinde din side (tager 2 minutter):\n%s\n\nDette er helt valgfrit og du kan afbryde forbindelsen når som helst.\n\nSpørgsmål? Svar på denne email.\n\nMed venlig hilsen,\n%s", 'centershop_txtdomain'),
            $shop_name,
            $magic_link,
            $mall_name
        );
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        $sent = wp_mail($shop_email, $subject, $message, $headers);
        
        if ($sent) {
            wp_send_json_success(array('message' => sprintf(__('Email sendt til %s', 'centershop_txtdomain'), $shop_email)));
        } else {
            wp_send_json_error(array('message' => __('Kunne ikke sende email', 'centershop_txtdomain')));
        }
    }
    
    /**
     * AJAX: Disconnect shop from Facebook
     */
    public function ajax_disconnect_shop() {
        check_ajax_referer('centershop_fb_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Ingen tilladelse', 'centershop_txtdomain')));
        }
        
        $connection_id = isset($_POST['connection_id']) ? intval($_POST['connection_id']) : 0;
        
        if (!$connection_id) {
            wp_send_json_error(array('message' => __('Mangler forbindelse ID', 'centershop_txtdomain')));
        }
        
        $connections = CenterShop_FB_Connections::get_instance();
        $result = $connections->disconnect_page($connection_id);
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Kunne ikke afbryde forbindelse', 'centershop_txtdomain')));
        }
        
        wp_send_json_success(array('message' => __('Forbindelse afbrudt', 'centershop_txtdomain')));
    }
}
