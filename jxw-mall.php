<?php
/**
 * Plugin Name:       JXW Mall
 * Plugin URI:        https://jaxweb.dk
 * Description:       CenterShop plugin til administration af butikker, åbningstider, grundplan, Instagram-feed, Facebook-feed og SoMe Planner.
 * Version:           1.0.0
 * Author:            JaxWeb
 * Author URI:        https://jaxweb.dk
 * Text Domain:       centershop_txtdomain
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─── Konstanter ──────────────────────────────────────────────────────────────

define( 'JXW_MALL_VERSION',  '1.0.0' );
define( 'JXW_MALL_DIR',      plugin_dir_path( __FILE__ ) );
define( 'JXW_MALL_URL',      plugin_dir_url( __FILE__ ) );
define( 'JXW_MALL_BASENAME', plugin_basename( __FILE__ ) );

// ─── Klasser ─────────────────────────────────────────────────────────────────

// Core
require_once JXW_MALL_DIR . 'includes/class-admin-menu.php';
require_once JXW_MALL_DIR . 'includes/class-settings.php';
require_once JXW_MALL_DIR . 'includes/class-floorplan.php';

// Funktionsfiler (registrerer egne hooks ved include)
require_once JXW_MALL_DIR . 'includes/functions-categorythumbnail.php';
require_once JXW_MALL_DIR . 'includes/functions-post-shop-connection.php';
require_once JXW_MALL_DIR . 'includes/functions-branch-shortcode.php';
require_once JXW_MALL_DIR . 'includes/functions-shopping-hours-shortcodes.php';

// Shopping hours
require_once JXW_MALL_DIR . 'includes/functions-shopping-hours.php';

// Shop-adgang
require_once JXW_MALL_DIR . 'includes/shop-access/class-shop-roles.php';
require_once JXW_MALL_DIR . 'includes/shop-access/class-shop-user-admin.php';

// Facebook Feed
require_once JXW_MALL_DIR . 'includes/facebook-feed/class-fb-post-type.php';
require_once JXW_MALL_DIR . 'includes/facebook-feed/class-fb-database.php';
require_once JXW_MALL_DIR . 'includes/facebook-feed/class-fb-api-handler.php';
require_once JXW_MALL_DIR . 'includes/facebook-feed/class-fb-importer.php';
require_once JXW_MALL_DIR . 'includes/facebook-feed/class-fb-cron.php';
require_once JXW_MALL_DIR . 'includes/facebook-feed/class-fb-shortcodes.php';
require_once JXW_MALL_DIR . 'includes/facebook-feed/class-fb-settings.php';
require_once JXW_MALL_DIR . 'includes/facebook-feed/class-fb-posts-list.php';
require_once JXW_MALL_DIR . 'includes/facebook-feed/class-fb-connections.php';
require_once JXW_MALL_DIR . 'includes/facebook-feed/class-fb-tenant-auth.php';

// Instagram Feed
require_once JXW_MALL_DIR . 'includes/instagram-feed/class-ig-post-type.php';
require_once JXW_MALL_DIR . 'includes/instagram-feed/class-ig-database.php';
require_once JXW_MALL_DIR . 'includes/instagram-feed/class-ig-api-handler.php';
require_once JXW_MALL_DIR . 'includes/instagram-feed/class-ig-importer.php';
require_once JXW_MALL_DIR . 'includes/instagram-feed/class-ig-cron.php';
require_once JXW_MALL_DIR . 'includes/instagram-feed/class-ig-shortcodes.php';
require_once JXW_MALL_DIR . 'includes/instagram-feed/class-ig-settings.php';
require_once JXW_MALL_DIR . 'includes/instagram-feed/class-ig-posts-list.php';

// SoMe Planner
require_once JXW_MALL_DIR . 'includes/some-planner/class-planner-post-type.php';
require_once JXW_MALL_DIR . 'includes/some-planner/class-planner-templates.php';
require_once JXW_MALL_DIR . 'includes/some-planner/class-planner-calendar.php';

// ─── Butikker CPT ────────────────────────────────────────────────────────────
// functions-cpt-butikker.php definerer $labels/$args på filniveau og mangler
// et register_post_type()-kald — vi wrapper det i en init-hook her.

add_action( 'init', 'jxw_mall_register_butiksside_cpt' );

function jxw_mall_register_butiksside_cpt() {
    require_once JXW_MALL_DIR . 'includes/functions-cpt-butikker.php';

    register_post_type( 'butiksside', $args );
}

// ─── Initialisering af singleton-klasser ─────────────────────────────────────

add_action( 'plugins_loaded', 'jxw_mall_init' );

function jxw_mall_init() {
    // Core
    CenterShop_Admin_Menu::get_instance();
    CenterShop_Settings::get_instance();
    CenterShop_FloorPlan::get_instance();

    // Shop-adgang
    CenterShop_Shop_Roles::get_instance();
    CenterShop_Shop_User_Admin::get_instance();

    // Facebook Feed
    CenterShop_FB_Post_Type::get_instance();
    CenterShop_FB_Database::get_instance();
    CenterShop_FB_Importer::get_instance();
    CenterShop_FB_Cron::get_instance();
    CenterShop_FB_Shortcodes::get_instance();
    CenterShop_FB_Settings::get_instance();
    CenterShop_FB_Posts_List::get_instance();
    CenterShop_FB_Connections::get_instance();
    CenterShop_FB_Tenant_Auth::get_instance();

    // Instagram Feed
    CenterShop_IG_Post_Type::get_instance();
    CenterShop_IG_Database::get_instance();
    CenterShop_IG_Importer::get_instance();
    CenterShop_IG_Cron::get_instance();
    CenterShop_IG_Shortcodes::get_instance();
    CenterShop_IG_Settings::get_instance();
    CenterShop_IG_Posts_List::get_instance();

    // SoMe Planner
    CenterShop_Planner_Post_Type::get_instance();
    CenterShop_Planner_Templates::get_instance();
    CenterShop_Planner_Calendar::get_instance();

    // Shopping Hours
    $shopping_hours = new CenterShop_Shopping_Hours();
    add_action( 'admin_menu', array( $shopping_hours, 'shoppinghours_add_menu' ) );
}

// ─── Gutenberg-blokke ────────────────────────────────────────────────────────

add_action( 'init', 'jxw_mall_register_blocks' );

function jxw_mall_register_blocks() {
    $blocks = array(
        'facebook-feed',
        'floorplan',
        'instagram-feed',
        'opening-hours',
        'shop-list',
        'shop-logo-carousel',
        'single-shop',
    );

    foreach ( $blocks as $block ) {
        register_block_type( JXW_MALL_DIR . 'blocks/' . $block );
    }
}
