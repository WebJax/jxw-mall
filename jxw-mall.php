<?php
/*
Plugin Name: JXW Mall
Plugin URI:  http://www.jaxweb.dk
Description: Butiksoversigt, SoMe Planner og Facebook Feed for butikscentre
Version:     2.0.0
Author:      jaxweb.dk
Author URI:  http://www.jaxweb.dk
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: centershop_txtdomain
Domain Path: /languages
*/

/*
CenterButikker is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
 
CenterButikker is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with CenterButikker. If not, see {URI to Plugin License}.
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('CENTERSHOP_VERSION', '2.0.0');
define('CENTERSHOP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CENTERSHOP_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Load all plugin modules
 */
function centershop_load_modules() {
    // Core modules
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/class-admin-menu.php';
    
    // Shop Access modules
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/shop-access/class-shop-roles.php';
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/shop-access/class-shop-user-admin.php';
    
    // SoMe Planner modules
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/some-planner/class-planner-post-type.php';
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/some-planner/class-planner-calendar.php';
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/some-planner/class-planner-templates.php';
    
    // Facebook Feed modules
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/facebook-feed/class-fb-database.php';
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/facebook-feed/class-fb-post-type.php';
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/facebook-feed/class-fb-api-handler.php';
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/facebook-feed/class-fb-importer.php';
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/facebook-feed/class-fb-cron.php';
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/facebook-feed/class-fb-settings.php';
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/facebook-feed/class-fb-posts-list.php';
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/facebook-feed/class-fb-shortcodes.php';
    
    // Post-Shop connection module
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/functions-post-shop-connection.php';
    
    // Initialize modules
    CenterShop_Admin_Menu::get_instance();
    CenterShop_Shop_Roles::get_instance();
    CenterShop_Shop_User_Admin::get_instance();
    CenterShop_Planner_Post_Type::get_instance();
    CenterShop_Planner_Calendar::get_instance();
    CenterShop_Planner_Templates::get_instance();
    CenterShop_FB_Database::get_instance();
    CenterShop_FB_Post_Type::get_instance();
    CenterShop_FB_API_Handler::get_instance();
    CenterShop_FB_Importer::get_instance();
    CenterShop_FB_Cron::get_instance();
    CenterShop_FB_Settings::get_instance();
    CenterShop_FB_Posts_List::get_instance();
}
add_action('plugins_loaded', 'centershop_load_modules');

/**
 * Plugin activation hook
 */
function centershop_activate() {
    // Create Facebook posts database table
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/facebook-feed/class-fb-database.php';
    CenterShop_FB_Database::create_table();
    
    // Setup cron jobs
    if (!wp_next_scheduled('centershop_fb_import_cron')) {
        wp_schedule_event(strtotime('03:00:00'), 'daily', 'centershop_fb_import_cron');
    }
}
register_activation_hook(__FILE__, 'centershop_activate');

/**
 * Plugin deactivation hook
 */
function centershop_deactivate() {
    // Clear cron jobs
    wp_clear_scheduled_hook('centershop_fb_import_cron');
}
register_deactivation_hook(__FILE__, 'centershop_deactivate');

/**
 * Track shop manager last login
 */
function centershop_track_login($user_login, $user) {
    if (in_array('shop_manager', (array) $user->roles)) {
        update_user_meta($user->ID, 'centershop_last_login', time());
    }
}
add_action('wp_login', 'centershop_track_login', 10, 2);

function centershop_setup_post_types()
{
    // register the three custom post type

  require_once(dirname( __FILE__ ) . "/includes/functions-cpt-butikker.php");
  register_post_type( 'butiksside', $args );
  add_action('save_post', 'save_butiks_meta', 999, 2); // save the custom fields
  
  // Initialize shopping hours functionality
  require_once(dirname( __FILE__ ) . "/includes/functions-shopping-hours.php");
  $shopping_hours = new CenterShop_Shopping_Hours();
  $shopping_hours->admin_init();
  
	// enqueue datepicker and styles and special style to overcome minor z-index-problem with TinyMCE
	function enqueue_datepicker_ui_for_associations() {
		wp_register_script( 
			'custom-datepicker', 
			plugins_url( '/js/pubforce-admin.js', __FILE__ ), 
			array('jquery', 'jquery-ui-core', 'jquery-ui-datepicker'),
			time(),
			true
		);	
		
		$imageurl = plugins_url( '/js/date-picker.png', __FILE__ );		
		wp_localize_script ( 'custom-datepicker', 'pngurl', $imageurl );
		
		wp_enqueue_script ( 'custom-datepicker' );
		
		wp_enqueue_style( 'jquery-ui-datepicker' );
		wp_enqueue_style( 'centershop-zindex-styles', plugins_url('/css/centershop-zindex-styles.css', __FILE__ ) );
		wp_enqueue_media();
		wp_enqueue_script('cpt-get-logo-cpt-js',  plugins_url('/js/get-logo-cpt.js', __FILE__ ) );
	}
	
	add_action('admin_enqueue_scripts','enqueue_datepicker_ui_for_associations');
	
	require_once(dirname( __FILE__ ) .  "/includes/functions-categorythumbnail.php");
	
	require_once(dirname( __FILE__ ) .  "/includes/functions-branch-shortcode.php");
	
	wp_enqueue_style( 'centershop-frontend-styles', plugins_url('/css/centershop-frontend-styles.css', __FILE__ ) );
	
}
add_action( 'init', 'centershop_setup_post_types' );

// Enqueue shared butik styles on frontend
function centershop_enqueue_styles() {
	wp_enqueue_style( 'centershop-butik-styles', plugins_url('/css/centershop-butik-styles.css', __FILE__ ), array(), filemtime(plugin_dir_path(__FILE__) . 'css/centershop-butik-styles.css') );
}
add_action( 'wp_enqueue_scripts', 'centershop_enqueue_styles' );

// Override theme templates with plugin templates
function centershop_template_include( $template ) {
	// Check if we're on a single butiksside post
	if ( is_singular( 'butiksside' ) ) {
		$plugin_template = plugin_dir_path( __FILE__ ) . 'templates/single-butiksside.php';
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}
	}
	
	// Check if we're on the butikslister page template
	if ( is_page_template( 'visbutikslister.php' ) ) {
		$plugin_template = plugin_dir_path( __FILE__ ) . 'templates/visbutikslister.php';
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}
	}
	
	return $template;
}
add_filter( 'template_include', 'centershop_template_include', 99 );

// Add plugin templates to page template dropdown
function centershop_add_page_templates( $templates ) {
	$templates['visbutikslister.php'] = 'Butiksliste';
	return $templates;
}
add_filter( 'theme_page_templates', 'centershop_add_page_templates' );

// Load plugin page templates
function centershop_load_plugin_templates( $template ) {
	if ( get_page_template_slug() === 'visbutikslister.php' ) {
		$plugin_template = plugin_dir_path( __FILE__ ) . 'templates/visbutikslister.php';
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}
	}
	return $template;
}
add_filter( 'page_template', 'centershop_load_plugin_templates' );

// Register Gutenberg blocks
function centershop_register_blocks() {
    // Register shared butik styles for blocks
    wp_register_style(
        'centershop-butik-styles',
        plugins_url('css/centershop-butik-styles.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'css/centershop-butik-styles.css')
    );
    
    // Register shop list block
    wp_register_script(
        'centershop-shop-list-editor',
        plugins_url('blocks/shop-list/index.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-data'),
        filemtime(plugin_dir_path(__FILE__) . 'blocks/shop-list/index.js')
    );
    
    register_block_type( __DIR__ . '/blocks/shop-list', array(
        'render_callback' => 'centershop_render_shop_list_block',
        'editor_script' => 'centershop-shop-list-editor',
        'style' => 'centershop-butik-styles'
    ));
    
    // Register single shop block
    wp_register_script(
        'centershop-single-shop-editor',
        plugins_url('blocks/single-shop/index.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-data'),
        filemtime(plugin_dir_path(__FILE__) . 'blocks/single-shop/index.js')
    );
    
    register_block_type( __DIR__ . '/blocks/single-shop', array(
        'render_callback' => 'centershop_render_single_shop_block',
        'editor_script' => 'centershop-single-shop-editor',
        'style' => 'centershop-butik-styles'
    ));
    
    // Register logo carousel block
    wp_register_script(
        'centershop-logo-carousel-editor',
        plugins_url('blocks/shop-logo-carousel/index.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-data'),
        filemtime(plugin_dir_path(__FILE__) . 'blocks/shop-logo-carousel/index.js')
    );
    
    wp_register_style(
        'centershop-logo-carousel-style',
        plugins_url('blocks/shop-logo-carousel/style.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'blocks/shop-logo-carousel/style.css')
    );
    
    register_block_type( __DIR__ . '/blocks/shop-logo-carousel', array(
        'render_callback' => 'centershop_render_logo_carousel_block',
        'editor_script' => 'centershop-logo-carousel-editor',
        'style' => 'centershop-logo-carousel-style'
    ));
    
    // Register opening hours block
    wp_register_script(
        'centershop-opening-hours-editor',
        plugins_url('blocks/opening-hours/index.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components'),
        filemtime(plugin_dir_path(__FILE__) . 'blocks/opening-hours/index.js')
    );
    
    wp_register_style(
        'centershop-opening-hours-style',
        plugins_url('blocks/opening-hours/style.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'blocks/opening-hours/style.css')
    );
    
    register_block_type( __DIR__ . '/blocks/opening-hours', array(
        'render_callback' => 'centershop_render_opening_hours_block',
        'editor_script' => 'centershop-opening-hours-editor',
        'style' => 'centershop-opening-hours-style'
    ));
}
add_action( 'init', 'centershop_register_blocks' );

// Render callbacks for blocks
function centershop_render_logo_carousel_block( $attributes, $content ) {
    ob_start();
    include plugin_dir_path( __FILE__ ) . 'blocks/shop-logo-carousel/render.php';
    return ob_get_clean();
}

function centershop_render_opening_hours_block( $attributes, $content ) {
    ob_start();
    include plugin_dir_path( __FILE__ ) . 'blocks/opening-hours/render.php';
    return ob_get_clean();
}

function centershop_render_shop_list_block( $attributes, $content ) {
    ob_start();
    include plugin_dir_path( __FILE__ ) . 'blocks/shop-list/render.php';
    return ob_get_clean();
}

function centershop_render_single_shop_block( $attributes, $content ) {
    ob_start();
    include plugin_dir_path( __FILE__ ) . 'blocks/single-shop/render.php';
    return ob_get_clean();
}

// Register meta fields for REST API access
function centershop_register_meta_fields() {
    $meta_fields = array(
        'butik_payed_name',
        'butik_payed_adress',
        'butik_payed_postal',
        'butik_payed_city',
        'butik_payed_phone',
        'butik_payed_mail',
        'butik_payed_web',
        'butik_payed_fb',
        'butik_payed_insta',
        'allround-cpt_logo_id'
    );
    
    foreach ($meta_fields as $field) {
        register_post_meta('butiksside', $field, array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
        ));
    }
}
add_action('init', 'centershop_register_meta_fields');
 
function centershop_install()
{
    // Load required classes
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/shop-access/class-shop-roles.php';
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/facebook-feed/class-fb-cron.php';
    
    // trigger our function that registers the custom post type
    centershop_setup_post_types();
    
    // Create shop_manager role
    CenterShop_Shop_Roles::create_role();
    
    // Schedule Facebook cron
    CenterShop_FB_Cron::schedule();
 
    // clear the permalinks after the post type has been registered
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'centershop_install' );
/**
 * Plugin uninstall - remove role (optional)
 */
function centershop_uninstall() {
    // Load required class
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/shop-access/class-shop-roles.php';
    
    CenterShop_Shop_Roles::remove_role();
}
// register_uninstall_hook( __FILE__, 'centershop_uninstall' );

function centershop_export_emails_page() {
    ?>
    <div class="wrap">
        <h1>Eksporter butik e-mails</h1>
        <p>Klik på knappen nedenfor for at eksportere alle e-mail-adresser fra butikkerne til en tekstfil.</p>
        <form method="post">
            <?php wp_nonce_field('centershop_export_emails', 'centershop_email_export_nonce'); ?>
            <input type="submit" name="centershop_export_emails" class="button button-primary" value="Eksporter e-mails">
        </form>
    </div>
    <?php
    
    // Process export if form is submitted
    if (isset($_POST['centershop_export_emails']) && check_admin_referer('centershop_export_emails', 'centershop_email_export_nonce')) {
        centershop_process_email_export();
    }
}

function centershop_process_email_export() {
    // Get all shop posts
    $shops = get_posts(array(
        'post_type' => 'butiksside',
        'numberposts' => -1,
        'post_status' => 'publish'
    ));
    
    $emails = array();
    
    // Extract emails from each shop
    foreach ($shops as $shop) {
        $email = get_post_meta($shop->ID, 'butik_payed_mail', true);
        $shop_name = get_the_title($shop->ID);
        $emails[] = "$shop_name: $email";
    }
    
    if (empty($emails)) {
        echo '<div class="notice notice-warning"><p>Ingen gyldige e-mail-adresser fundet.</p></div>';
        return;
    }
    
	foreach ($emails as $email) {
		echo $email . '<br>';
	}
}