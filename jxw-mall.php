<?php
/*
Plugin Name: Centershop
Plugin URI:  http://www.jaxweb.dk
Description: Butiksoversigt i Dianalund Centret med kort og åbningstider
Version:     1.9.0
Author:      jaxweb.dk
Author URI:  http://www.jaxweb.dk
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: centershop_txtdomain
Domain Path: /languages
*/

/*
Centershop is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
 
Centershop is distributed in the hope that it will be useful,
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
define('CENTERSHOP_VERSION', '1.9.0');
define('CENTERSHOP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CENTERSHOP_PLUGIN_URL', plugin_dir_url(__FILE__));
require_once CENTERSHOP_PLUGIN_DIR . 'includes/plugin-lifecycle.php';
require_once CENTERSHOP_PLUGIN_DIR . 'includes/functions-shop-availability.php';

/**
 * Load all plugin modules
 */
function centershop_load_modules() {
    // Core modules
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/class-admin-menu.php';
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/class-settings.php';
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/class-floorplan.php';

    // Post-Shop connection module
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/functions-post-shop-connection.php';
    require_once CENTERSHOP_PLUGIN_DIR . 'includes/functions-facebook-page-id.php';
    
    // Initialize modules
    CenterShop_Admin_Menu::get_instance();
    CenterShop_Settings::get_instance();
    CenterShop_FloorPlan::get_instance();
}
add_action('plugins_loaded', 'centershop_load_modules');

/**
 * Default path segment for a single shop.
 */
function centershop_default_shop_slug(): string {
  return 'butiksside';
}

/**
 * Slugs that would collide with WordPress core routes.
 */
function centershop_shop_slug_is_reserved( string $slug ): bool {
  $reserved = array(
    'wp-admin',
    'wp-content',
    'wp-includes',
    'wp-json',
    'feed',
    'embed',
    'page',
    'attachment',
    'category',
    'tag',
    'author',
    'search',
    'comments',
  );

  return in_array( $slug, $reserved, true );
}

/**
 * Permalink slug used in shop URLs, for example /butik/netto/.
 */
function centershop_get_shop_slug(): string {
  $slug = get_option( 'centershop_shop_slug', centershop_default_shop_slug() );

  if ( ! is_string( $slug ) ) {
    return centershop_default_shop_slug();
  }

  $slug = sanitize_title( $slug );

  if ( $slug === '' || centershop_shop_slug_is_reserved( $slug ) ) {
    return centershop_default_shop_slug();
  }

  return $slug;
}

/**
 * Sanitize the shop permalink slug from settings.
 *
 * @param mixed $value Raw submitted value.
 */
function centershop_sanitize_shop_slug( $value ): string {
  $raw  = is_string( $value ) ? $value : '';
  $slug = sanitize_title( $raw );

  if ( $slug === '' ) {
    add_settings_error(
      'centershop_shop_slug',
      'centershop_shop_slug_empty',
      __( 'Permalink-slug er tom eller ugyldig. Standardværdien "butiksside" er gemt.', 'centershop_txtdomain' ),
      'error'
    );

    return centershop_default_shop_slug();
  }

  if ( centershop_shop_slug_is_reserved( $slug ) ) {
    add_settings_error(
      'centershop_shop_slug',
      'centershop_shop_slug_reserved',
      sprintf(
        /* translators: %s: rejected slug */
        __( 'Sluggen "%s" er reserveret og kan ikke bruges. Den tidligere værdi er beholdt.', 'centershop_txtdomain' ),
        $slug
      ),
      'error'
    );

    return centershop_get_shop_slug();
  }

  return $slug;
}

function centershop_setup_post_types()
{
    // register butiksside post type

  require_once(dirname( __FILE__ ) . "/includes/functions-shop-availability.php");
  require_once(dirname( __FILE__ ) . "/includes/functions-cpt-butikker.php");
  require_once(dirname( __FILE__ ) . "/includes/functions-opening-hours-v2.php");
  $args['rewrite'] = array( 'slug' => centershop_get_shop_slug() );
  register_post_type( 'butiksside', $args );

  // Shop URLs are stored in the rewrite option. Refresh them when the saved
  // slug changes, otherwise /butik/butiknavn/ stays a 404.
  if ( get_option( 'centershop_butiksside_rewrite' ) !== centershop_get_shop_slug() ) {
    add_action( 'init', 'centershop_flush_butiksside_rewrites', 99 );
  }
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

		// Opening Hours v2 admin assets (only on butiksside edit screens)
		$screen = get_current_screen();
		if ( $screen && $screen->post_type === 'butiksside' && in_array( $screen->base, [ 'post', 'post-new' ], true ) ) {
			wp_enqueue_style(
				'jxw-opening-hours-admin',
				plugins_url( '/css/opening-hours-admin.css', __FILE__ ),
				[],
				filemtime( plugin_dir_path( __FILE__ ) . 'css/opening-hours-admin.css' )
			);
			wp_enqueue_script(
				'jxw-opening-hours-admin',
				plugins_url( '/js/opening-hours-admin.js', __FILE__ ),
				[],
				filemtime( plugin_dir_path( __FILE__ ) . 'js/opening-hours-admin.js' ),
				true
			);
			wp_enqueue_script(
				'centershop-facebook-page-id-admin',
				plugins_url( '/js/facebook-page-id-admin.js', __FILE__ ),
				array( 'jquery' ),
				filemtime( plugin_dir_path( __FILE__ ) . 'js/facebook-page-id-admin.js' ),
				true
			);
			wp_localize_script(
				'centershop-facebook-page-id-admin',
				'centershopFbPageId',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'centershop_fb_page_id' ),
					'i18n'    => array(
						'missingUrl' => __( 'Indtast først en Facebook-adresse.', 'centershop_txtdomain' ),
						'working'    => __( 'Henter…', 'centershop_txtdomain' ),
						'success'    => __( 'Page ID hentet.', 'centershop_txtdomain' ),
						'error'      => __( 'Kunne ikke hente Page ID.', 'centershop_txtdomain' ),
					),
				)
			);
		}
	}
	
	add_action('admin_enqueue_scripts','enqueue_datepicker_ui_for_associations');

	require_once(dirname( __FILE__ ) .  "/includes/functions-branch-shortcode.php");
	
	wp_enqueue_style( 'centershop-frontend-styles', plugins_url('/css/centershop-frontend-styles.css', __FILE__ ) );
	
}
add_action( 'init', 'centershop_setup_post_types' );

/**
 * Rewrite shop permalinks once, after butiksside is registered.
 */
function centershop_flush_butiksside_rewrites() {
  flush_rewrite_rules( false );
  update_option( 'centershop_butiksside_rewrite', centershop_get_shop_slug(), false );
}

// Enqueue shared butik styles on frontend
function centershop_enqueue_styles() {
	wp_enqueue_style( 'centershop-butik-styles', plugins_url('/css/centershop-butik-styles.css', __FILE__ ), array(), filemtime(plugin_dir_path(__FILE__) . 'css/centershop-butik-styles.css') );
}
add_action( 'wp_enqueue_scripts', 'centershop_enqueue_styles' );

// Override theme templates with plugin templates
function centershop_template_include( string $plugin_template ) {
	// Check if we're on a single butiksside post
	if ( is_singular( 'butiksside' ) ) {
		$plugin_template = plugin_dir_path( __FILE__ ) . 'templates/single-butiksside.php';
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}
	}
	
	// Check if we're on the butikslister page template
	if ( 'visbutikslister.php' === get_page_template_slug() ) {
		$plugin_template = plugin_dir_path( __FILE__ ) . 'templates/visbutikslister.php';
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}
	}
	
	return $plugin_template;
}
add_filter( 'template_include', 'centershop_template_include', 99 );

// Add plugin templates to page template dropdown
function centershop_add_page_templates( array $templates ) {
	$templates['visbutikslister.php'] = 'Butiksliste';
	return $templates;
}
add_filter( 'theme_page_templates', 'centershop_add_page_templates' );

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
    
    // Register read-also block
    wp_register_script(
        'centershop-read-also-editor',
        plugins_url('blocks/read-also/index.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-data'),
        filemtime(plugin_dir_path(__FILE__) . 'blocks/read-also/index.js')
    );
    
    wp_register_style(
        'centershop-read-also-style',
        plugins_url('blocks/read-also/style.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'blocks/read-also/style.css')
    );
    
    register_block_type( __DIR__ . '/blocks/read-also', array(
        'render_callback' => 'centershop_render_read_also_block',
        'editor_script' => 'centershop-read-also-editor',
        'style' => 'centershop-read-also-style'
    ));

    wp_register_script(
        'centershop-floorplan-editor',
        plugins_url('blocks/floorplan/index.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render'),
        filemtime(plugin_dir_path(__FILE__) . 'blocks/floorplan/index.js')
    );

    register_block_type( __DIR__ . '/blocks/floorplan', array(
        'render_callback' => 'centershop_render_floorplan_block',
        'editor_script' => 'centershop-floorplan-editor',
        'script' => 'centershop-floorplan',
        'style' => 'centershop-floorplan'
    ));
}
add_action( 'init', 'centershop_register_blocks' );

// Render callbacks for blocks
function centershop_render_logo_carousel_block( array $attributes, string $content ) {
    ob_start();
    include plugin_dir_path( __FILE__ ) . 'blocks/shop-logo-carousel/render.php';
    return ob_get_clean();
}

function centershop_render_opening_hours_block( array $attributes, string $content ) {
    ob_start();
    include plugin_dir_path( __FILE__ ) . 'blocks/opening-hours/render.php';
    return ob_get_clean();
}

function centershop_render_shop_list_block( array $attributes, string $content ) {
    ob_start();
    include plugin_dir_path( __FILE__ ) . 'blocks/shop-list/render.php';
    return ob_get_clean();
}

function centershop_render_single_shop_block( array $attributes, string $content ) {
    ob_start();
    include plugin_dir_path( __FILE__ ) . 'blocks/single-shop/render.php';
    return ob_get_clean();
}

function centershop_render_read_also_block( array $attributes, string $content ) {
    ob_start();
    include plugin_dir_path( __FILE__ ) . 'blocks/read-also/render.php';
    return ob_get_clean();
}

function centershop_render_floorplan_block( array $attributes, string $content ) {
    ob_start();
    include plugin_dir_path( __FILE__ ) . 'blocks/floorplan/render.php';
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
        'butik_payed_mail_show',
        'butik_payed_web',
        'butik_payed_fb',
        'butik_payed_fb_id',
        'butik_payed_insta',
        'allround-cpt_logo_id',
        'butik_availability'
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
 
centershop_register_lifecycle_hooks( __FILE__ );

/**
 * Published shops, sorted by title.
 *
 * @return WP_Post[]
 */
function centershop_get_export_shops(): array {
	$shops = get_posts(
		array(
			'post_type'   => 'butiksside',
			'numberposts' => -1,
			'post_status' => 'publish',
			'orderby'     => 'title',
			'order'       => 'ASC',
		)
	);

	return is_array( $shops ) ? $shops : array();
}

/**
 * Read a shop meta value as a string.
 */
function centershop_export_meta_string( int $post_id, string $key ): string {
	$value = get_post_meta( $post_id, $key, true );

	return is_string( $value ) ? $value : '';
}

/**
 * Column headings for the shop CSV, in output order.
 *
 * @return array<string, string>
 */
function centershop_get_shop_export_columns(): array {
	return array(
		'titel'            => __( 'Titel', 'centershop_txtdomain' ),
		'butiksnavn'       => __( 'Butiksnavn', 'centershop_txtdomain' ),
		'adresse'          => __( 'Adresse', 'centershop_txtdomain' ),
		'postnr'           => __( 'Postnr', 'centershop_txtdomain' ),
		'by'               => __( 'By', 'centershop_txtdomain' ),
		'telefon'          => __( 'Telefon', 'centershop_txtdomain' ),
		'email'            => __( 'E-mail', 'centershop_txtdomain' ),
		'hjemmeside'       => __( 'Hjemmeside', 'centershop_txtdomain' ),
		'facebook'         => __( 'Facebook', 'centershop_txtdomain' ),
		'facebook_page_id' => __( 'Facebook Page ID', 'centershop_txtdomain' ),
		'instagram'        => __( 'Instagram', 'centershop_txtdomain' ),
		'kategorier'       => __( 'Kategorier', 'centershop_txtdomain' ),
		'status'           => __( 'Status', 'centershop_txtdomain' ),
		'link'             => __( 'Link', 'centershop_txtdomain' ),
	);
}

/**
 * One spreadsheet row per published shop.
 *
 * @return array<int, array<string, string>>
 */
function centershop_get_shop_export_rows(): array {
	$availability = function_exists( 'centershop_get_shop_availability_options' )
		? centershop_get_shop_availability_options()
		: array();
	$rows         = array();

	foreach ( centershop_get_export_shops() as $shop ) {
		$terms = get_the_terms( $shop->ID, 'category' );
		$names = array();

		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$names[] = $term->name;
			}
		}

		$status_key = centershop_export_meta_string( $shop->ID, 'butik_availability' );
		$status     = $availability[ $status_key ] ?? ( $availability['occupied'] ?? '' );
		$permalink  = get_permalink( $shop );
		$email      = sanitize_email( centershop_export_meta_string( $shop->ID, 'butik_payed_mail' ) );

		$rows[] = array(
			'titel'            => get_the_title( $shop ),
			'butiksnavn'       => centershop_export_meta_string( $shop->ID, 'butik_payed_name' ),
			'adresse'          => centershop_export_meta_string( $shop->ID, 'butik_payed_adress' ),
			'postnr'           => centershop_export_meta_string( $shop->ID, 'butik_payed_postal' ),
			'by'               => centershop_export_meta_string( $shop->ID, 'butik_payed_city' ),
			'telefon'          => centershop_export_meta_string( $shop->ID, 'butik_payed_phone' ),
			'email'            => $email,
			'hjemmeside'       => centershop_export_meta_string( $shop->ID, 'butik_payed_web' ),
			'facebook'         => centershop_export_meta_string( $shop->ID, 'butik_payed_fb' ),
			'facebook_page_id' => centershop_export_meta_string( $shop->ID, 'butik_payed_fb_id' ),
			'instagram'        => centershop_export_meta_string( $shop->ID, 'butik_payed_insta' ),
			'kategorier'       => implode( ', ', $names ),
			'status'           => $status,
			'link'             => is_string( $permalink ) ? $permalink : '',
		);
	}

	return $rows;
}

/**
 * Published shops that have a usable email address.
 *
 * @return array<int, array{id: int, title: string, email: string}>
 */
function centershop_get_shop_email_choices(): array {
	$choices = array();

	foreach ( centershop_get_export_shops() as $shop ) {
		$email = sanitize_email( centershop_export_meta_string( $shop->ID, 'butik_payed_mail' ) );

		if ( $email === '' || ! is_email( $email ) ) {
			continue;
		}

		$choices[] = array(
			'id'    => (int) $shop->ID,
			'title' => get_the_title( $shop ),
			'email' => $email,
		);
	}

	return $choices;
}

/**
 * Unique shop emails, each followed by a semicolon, ready for a mail client.
 */
function centershop_get_shop_email_list(): string {
	$emails = array();

	foreach ( centershop_get_shop_email_choices() as $choice ) {
		$emails[ strtolower( $choice['email'] ) ] = $choice['email'];
	}

	if ( $emails === array() ) {
		return '';
	}

	return implode( '; ', $emails ) . ';';
}

/**
 * Published shops that have no usable email address.
 *
 * @return array<int, array{shop: WP_Post, email: string}>
 */
function centershop_get_shops_missing_email(): array {
	$missing = array();

	foreach ( centershop_get_export_shops() as $shop ) {
		$raw   = centershop_export_meta_string( $shop->ID, 'butik_payed_mail' );
		$email = sanitize_email( $raw );

		if ( $email !== '' && is_email( $email ) ) {
			continue;
		}

		$missing[] = array(
			'shop'  => $shop,
			'email' => $raw,
		);
	}

	return $missing;
}

/**
 * Stop spreadsheet apps from treating a cell as a formula.
 */
function centershop_csv_cell( string $value ): string {
	if ( $value !== '' && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ) {
		return "'" . $value;
	}

	return $value;
}

/**
 * Download every published shop as a semicolon-separated CSV.
 */
function centershop_download_shops_csv(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Du har ikke adgang til at eksportere butikker.', 'centershop_txtdomain' ) );
	}

	check_admin_referer( 'centershop_export_shops_csv', 'centershop_export_nonce' );

	$filename = 'butikker-' . wp_date( 'Y-m-d' ) . '.csv';

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

	$output = fopen( 'php://output', 'w' );

	if ( $output === false ) {
		wp_die( esc_html__( 'Kunne ikke oprette CSV-filen.', 'centershop_txtdomain' ) );
	}

	fwrite( $output, "\xEF\xBB\xBF" );

	$columns = centershop_get_shop_export_columns();
	fputcsv( $output, array_values( $columns ), ';', '"', '\\' );

	foreach ( centershop_get_shop_export_rows() as $row ) {
		$line = array();

		foreach ( array_keys( $columns ) as $key ) {
			$line[] = centershop_csv_cell( $row[ $key ] ?? '' );
		}

		fputcsv( $output, $line, ';', '"', '\\' );
	}

	fclose( $output );
	exit;
}
add_action( 'admin_post_centershop_export_shops_csv', 'centershop_download_shops_csv' );

/**
 * Admin page: CSV download and a copy-ready mail list.
 */
function centershop_export_emails_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$email_list    = centershop_get_shop_email_list();
	$email_count   = $email_list === '' ? 0 : count( explode( '; ', rtrim( $email_list, ';' ) ) );
	$shop_count    = count( centershop_get_export_shops() );
	$missing_email = centershop_get_shops_missing_email();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Eksporter butikker', 'centershop_txtdomain' ); ?></h1>
		<p><?php esc_html_e( 'Hent alle butiksoplysninger som CSV, eller kopiér mailadresserne herunder direkte ind i et mailprogram.', 'centershop_txtdomain' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'centershop_export_shops_csv', 'centershop_export_nonce' ); ?>
			<input type="hidden" name="action" value="centershop_export_shops_csv">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Eksporter til CSV', 'centershop_txtdomain' ); ?></button>
		</form>

		<h2><?php esc_html_e( 'Mailadresser', 'centershop_txtdomain' ); ?></h2>
		<?php if ( $email_list === '' ) : ?>
			<div class="notice notice-warning">
				<p><?php esc_html_e( 'Ingen gyldige e-mail-adresser fundet.', 'centershop_txtdomain' ); ?></p>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'Hver adresse står med semikolon efter, så listen kan sættes ind i modtagerfeltet.', 'centershop_txtdomain' ); ?></p>
			<textarea id="centershop-email-list" class="large-text code" rows="8" readonly><?php echo esc_textarea( $email_list ); ?></textarea>
			<p>
				<button type="button" class="button" id="centershop-copy-emails"><?php esc_html_e( 'Kopiér mailadresser', 'centershop_txtdomain' ); ?></button>
				<span id="centershop-copy-emails-status" aria-live="polite"></span>
			</p>
			<p class="description">
				<?php
				$email_label = sprintf(
					/* translators: %d: number of email addresses */
					_n( '%d mailadresse', '%d mailadresser', $email_count, 'centershop_txtdomain' ),
					$email_count
				);
				$shop_label = sprintf(
					/* translators: %d: number of published shops */
					_n( '%d publiceret butik', '%d publicerede butikker', $shop_count, 'centershop_txtdomain' ),
					$shop_count
				);
				echo esc_html( $email_label . ' fra ' . $shop_label . '.' );
				?>
			</p>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Butikker uden mailadresse', 'centershop_txtdomain' ); ?></h2>
		<?php if ( $missing_email === array() ) : ?>
			<p><?php esc_html_e( 'Alle publicerede butikker har en mailadresse.', 'centershop_txtdomain' ); ?></p>
		<?php else : ?>
			<ul class="centershop-missing-emails">
				<?php foreach ( $missing_email as $item ) : ?>
					<?php
					$shop     = $item['shop'];
					$edit_url = get_edit_post_link( $shop->ID, 'raw' );
					?>
					<li>
						<?php echo esc_html( get_the_title( $shop ) ); ?>
						<?php if ( is_string( $edit_url ) && $edit_url !== '' ) : ?>
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Rediger', 'centershop_txtdomain' ); ?></a>
						<?php endif; ?>
						<?php if ( $item['email'] !== '' ) : ?>
							<span class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: stored email value that is not a valid address */
										__( 'Ugyldig adresse: %s', 'centershop_txtdomain' ),
										$item['email']
									)
								);
								?>
							</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
}
