<?php
/**
 * Floor Plan Management Class
 *
 * Håndterer upload af grundplan (billede) samt mapping af butikslokaler til butikker.
 *
 * Koordinater gemmes som brøker (0-1) af billedets bredde/højde, så de er uafhængige
 * af billedets opløsning. Ved visning omregnes de til billedets egne pixels, og
 * billede + polygoner ligger i ét <svg> med viewBox, så alt skalerer sammen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CenterShop_FloorPlan {

	/**
	 * Instance
	 *
	 * @var CenterShop_FloorPlan|null
	 */
	private static $instance = null;

	/**
	 * Option names
	 */
	const OPTION_NAME = 'centershop_floorplan_data';
	const OPTION_SVG  = 'centershop_floorplan_svg';
	const OPTION_SIZE = 'centershop_floorplan_size';

	/**
	 * Maksimalt antal områder og punkter pr. område (beskytter mod misbrug).
	 */
	const MAX_AREAS  = 500;
	const MAX_POINTS = 200;

	/**
	 * Get instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_save_floorplan_svg', array( $this, 'ajax_save_svg' ) );
		add_action( 'wp_ajax_save_floorplan_areas', array( $this, 'ajax_save_areas' ) );
		add_action( 'wp_ajax_get_floorplan_data', array( $this, 'ajax_get_data' ) );
		add_action( 'wp_ajax_delete_floorplan_area', array( $this, 'ajax_delete_area' ) );

		// Frontend assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Shortcode.
		add_shortcode( 'centershop_floorplan', array( $this, 'floorplan_shortcode' ) );

		// AJAX for frontend.
		add_action( 'wp_ajax_search_shops', array( $this, 'ajax_search_shops' ) );
		add_action( 'wp_ajax_nopriv_search_shops', array( $this, 'ajax_search_shops' ) );

		// SVG-upload (kun for administratorer).
		add_filter( 'upload_mimes', array( $this, 'allow_svg_upload' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_svg_mime_type' ), 10, 4 );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_submenu_page(
			CenterShop_Admin_Menu::MENU_SLUG,
			__( 'Grundplan', 'centershop_txtdomain' ),
			__( 'Grundplan', 'centershop_txtdomain' ),
			'manage_options',
			'centershop-floorplan',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'centershop_page_centershop-floorplan' !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'centershop-floorplan-admin',
			CENTERSHOP_PLUGIN_URL . 'css/floorplan-admin.css',
			array(),
			CENTERSHOP_VERSION
		);

		wp_enqueue_script(
			'centershop-floorplan-admin',
			CENTERSHOP_PLUGIN_URL . 'js/floorplan-admin.js',
			array( 'jquery' ),
			CENTERSHOP_VERSION,
			true
		);

		$size  = $this->get_size();
		$shops = array();
		foreach ( $this->get_all_shops() as $shop ) {
			$shops[] = array(
				'id'    => (int) $shop->ID,
				'title' => $shop->post_title,
			);
		}

		wp_localize_script(
			'centershop-floorplan-admin',
			'centershopFloorplan',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'centershop_floorplan_nonce' ),
				'image'   => array(
					'url'    => get_option( self::OPTION_SVG, '' ),
					'width'  => $size['width'],
					'height' => $size['height'],
				),
				'areas'   => $this->get_areas(),
				'shops'   => $shops,
				'strings' => array(
					'selectShop'      => __( 'Vælg butik', 'centershop_txtdomain' ),
					'noShop'          => __( 'Ingen butik', 'centershop_txtdomain' ),
					'deleteArea'      => __( 'Slet område', 'centershop_txtdomain' ),
					'confirmDelete'   => __( 'Er du sikker på at du vil slette dette område?', 'centershop_txtdomain' ),
					'saved'           => __( 'Grundplan gemt!', 'centershop_txtdomain' ),
					'unsaved'         => __( 'Der er ændringer, som ikke er gemt.', 'centershop_txtdomain' ),
					'error'           => __( 'Der opstod en fejl', 'centershop_txtdomain' ),
					'chooseImage'     => __( 'Vælg grundplan', 'centershop_txtdomain' ),
					'useImage'        => __( 'Brug dette billede', 'centershop_txtdomain' ),
					'noAreas'         => __( 'Ingen områder defineret endnu.', 'centershop_txtdomain' ),
					'areaLabel'       => __( 'Områdenavn', 'centershop_txtdomain' ),
					'areaShop'        => __( 'Tilknyt butik', 'centershop_txtdomain' ),
					'areaColor'       => __( 'Farve', 'centershop_txtdomain' ),
					'newArea'         => __( 'Lokale', 'centershop_txtdomain' ),
					'selectHint'      => __( 'Klik på et område i grundplanen for at redigere det. Træk i hjørnerne for at justere. Dobbeltklik på en kant for at tilføje et hjørne, og på et hjørne for at fjerne det.', 'centershop_txtdomain' ),
					'drawHint'        => __( 'Klik for at sætte hjørner. Klik på det første punkt (eller tryk Enter) for at afslutte. Backspace fjerner sidste punkt, Esc annullerer.', 'centershop_txtdomain' ),
					'importConfirm'   => __( 'Der findes allerede områder. OK = erstat dem, Annuller = tilføj de importerede.', 'centershop_txtdomain' ),
					'importDone'      => __( 'områder importeret. Husk at gemme.', 'centershop_txtdomain' ),
					'importInvalid'   => __( 'Filen har ikke det forventede format.', 'centershop_txtdomain' ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		if ( ! wp_style_is( 'centershop-butik-styles', 'registered' ) ) {
			wp_register_style(
				'centershop-butik-styles',
				CENTERSHOP_PLUGIN_URL . 'css/centershop-butik-styles.css',
				array(),
				CENTERSHOP_VERSION
			);
		}

		wp_register_style(
			'centershop-floorplan',
			CENTERSHOP_PLUGIN_URL . 'css/floorplan-frontend.css',
			array( 'centershop-butik-styles' ),
			CENTERSHOP_VERSION
		);

		wp_register_script(
			'centershop-floorplan',
			CENTERSHOP_PLUGIN_URL . 'js/floorplan-frontend.js',
			array(),
			CENTERSHOP_VERSION,
			true
		);

		wp_localize_script(
			'centershop-floorplan',
			'centershopFloorplanFrontend',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'strings' => array(
					'noResults' => __( 'Ingen butikker fundet', 'centershop_txtdomain' ),
					'notOnMap'  => __( 'ikke på kortet', 'centershop_txtdomain' ),
				),
			)
		);
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		$svg_url = get_option( self::OPTION_SVG, '' );
		$size    = $this->get_size();
		$ready   = ( $svg_url && $size['width'] > 0 && $size['height'] > 0 );

		?>
		<div class="wrap centershop-floorplan-admin">
			<h1><?php esc_html_e( 'Grundplan over centret', 'centershop_txtdomain' ); ?></h1>

			<div class="centershop-floorplan-content">
				<div class="floorplan-sidebar">
					<div class="postbox">
						<h2 class="hndle"><?php esc_html_e( 'Grundplan billede', 'centershop_txtdomain' ); ?></h2>
						<div class="inside">
							<button type="button" class="button button-primary" id="upload-svg-btn">
								<?php esc_html_e( 'Vælg billedfil', 'centershop_txtdomain' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Upload et billede af grundplanen (PNG, JPG eller SVG). Tip: 2400 px i bredden er rigeligt til web.', 'centershop_txtdomain' ); ?>
							</p>
							<?php if ( $svg_url && ! $ready ) : ?>
								<p class="notice notice-warning inline">
									<?php esc_html_e( 'Billedets størrelse er ikke gemt. Vælg billedet igen.', 'centershop_txtdomain' ); ?>
								</p>
							<?php elseif ( $ready ) : ?>
								<p class="svg-uploaded">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php
									printf(
										/* translators: 1: width, 2: height */
										esc_html__( 'Billede uploadet (%1$d × %2$d px)', 'centershop_txtdomain' ),
										(int) $size['width'],
										(int) $size['height']
									);
									?>
								</p>
							<?php endif; ?>
						</div>
					</div>

					<div class="postbox">
						<h2 class="hndle"><?php esc_html_e( 'Definerede områder', 'centershop_txtdomain' ); ?></h2>
						<div class="inside">
							<div id="areas-list"></div>
							<p>
								<button type="button" class="button" id="add-area-btn" <?php disabled( ! $ready ); ?>>
									<span class="dashicons dashicons-plus-alt"></span>
									<?php esc_html_e( 'Tegn nyt område', 'centershop_txtdomain' ); ?>
								</button>
								<button type="button" class="button button-primary" id="finish-area-btn" hidden>
									<?php esc_html_e( 'Afslut', 'centershop_txtdomain' ); ?>
								</button>
								<button type="button" class="button" id="cancel-area-btn" hidden>
									<?php esc_html_e( 'Annullér', 'centershop_txtdomain' ); ?>
								</button>
							</p>
							<p>
								<label class="button" for="import-areas-file">
									<?php esc_html_e( 'Importér områder (JSON)', 'centershop_txtdomain' ); ?>
								</label>
								<input type="file" id="import-areas-file" accept=".json,application/json" hidden>
							</p>
						</div>
					</div>

					<div class="postbox">
						<h2 class="hndle"><?php esc_html_e( 'Område detaljer', 'centershop_txtdomain' ); ?></h2>
						<div class="inside">
							<div id="area-editor"></div>
						</div>
					</div>
				</div>

				<div class="floorplan-main">
					<div class="floorplan-canvas-wrapper">
						<?php if ( $ready ) : ?>
							<div id="floorplan-canvas" data-mode="edit">
								<svg id="floorplan-canvas-svg"
									xmlns="http://www.w3.org/2000/svg"
									viewBox="0 0 <?php echo esc_attr( $size['width'] . ' ' . $size['height'] ); ?>">
									<image href="<?php echo esc_url( $svg_url ); ?>"
										x="0" y="0"
										width="<?php echo esc_attr( $size['width'] ); ?>"
										height="<?php echo esc_attr( $size['height'] ); ?>"></image>
									<g id="fp-areas"></g>
									<g id="fp-draft"></g>
									<g id="fp-handles"></g>
								</svg>
							</div>
							<p class="description" id="floorplan-hint"></p>
						<?php else : ?>
							<div class="floorplan-empty">
								<span class="dashicons dashicons-images-alt2"></span>
								<p><?php esc_html_e( 'Upload et grundplan billede for at komme i gang', 'centershop_txtdomain' ); ?></p>
							</div>
						<?php endif; ?>
					</div>

					<div class="floorplan-actions">
						<button type="button" class="button button-primary button-large" id="save-floorplan" <?php disabled( ! $ready ); ?>>
							<?php esc_html_e( 'Gem grundplan', 'centershop_txtdomain' ); ?>
						</button>
						<span id="floorplan-status" role="status" aria-live="polite"></span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get all shops
	 */
	private function get_all_shops() {
		return get_posts(
			array(
				'post_type'      => 'butiksside',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);
	}

	/**
	 * Get areas
	 */
	private function get_areas() {
		$areas = get_option( self::OPTION_NAME, array() );
		return is_array( $areas ) ? array_values( $areas ) : array();
	}

	/**
	 * Get stored image size.
	 *
	 * @return array{width:int,height:int}
	 */
	private function get_size() {
		$size = get_option( self::OPTION_SIZE, array() );
		return array(
			'width'  => isset( $size['width'] ) ? (int) $size['width'] : 0,
			'height' => isset( $size['height'] ) ? (int) $size['height'] : 0,
		);
	}

	/**
	 * Find billedets størrelse (bitmap eller SVG).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array{width:int,height:int}|null
	 */
	private function detect_image_size( $attachment_id ) {
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
			return array(
				'width'  => (int) $meta['width'],
				'height' => (int) $meta['height'],
			);
		}

		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return null;
		}

		if ( 'image/svg+xml' === get_post_mime_type( $attachment_id ) ) {
			$head = file_get_contents( $file, false, null, 0, 4096 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $head ) {
				return null;
			}
			if ( preg_match( '/viewBox\s*=\s*"\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)\s*"/i', $head, $m ) ) {
				return array(
					'width'  => (int) round( (float) $m[1] ),
					'height' => (int) round( (float) $m[2] ),
				);
			}
			if ( preg_match( '/<svg[^>]*\swidth\s*=\s*"([\d.]+)(?:px)?"[^>]*\sheight\s*=\s*"([\d.]+)(?:px)?"/i', $head, $m ) ) {
				return array(
					'width'  => (int) round( (float) $m[1] ),
					'height' => (int) round( (float) $m[2] ),
				);
			}
			return null;
		}

		$info = function_exists( 'wp_getimagesize' ) ? wp_getimagesize( $file ) : getimagesize( $file );
		if ( is_array( $info ) && ! empty( $info[0] ) && ! empty( $info[1] ) ) {
			return array(
				'width'  => (int) $info[0],
				'height' => (int) $info[1],
			);
		}

		return null;
	}

	/**
	 * Rens en farve (kun hex accepteres).
	 *
	 * @param mixed $color Color value.
	 * @return string
	 */
	private function sanitize_color( $color ) {
		$clean = sanitize_hex_color( (string) $color );
		return $clean ? $clean : '';
	}

	/**
	 * Rens en punktstreng ("x,y x,y ...") med normaliserede værdier 0-1.
	 *
	 * @param mixed $points Points string.
	 * @return string Tom streng hvis ugyldig.
	 */
	private function sanitize_points( $points ) {
		$pairs = preg_split( '/\s+/', trim( (string) $points ) );
		if ( ! is_array( $pairs ) || count( $pairs ) < 3 || count( $pairs ) > self::MAX_POINTS ) {
			return '';
		}

		$out = array();
		foreach ( $pairs as $pair ) {
			if ( ! preg_match( '/^(\d*\.?\d+),(\d*\.?\d+)$/', $pair, $m ) ) {
				return '';
			}
			$x     = min( 1, max( 0, (float) $m[1] ) );
			$y     = min( 1, max( 0, (float) $m[2] ) );
			$out[] = number_format( $x, 5, '.', '' ) . ',' . number_format( $y, 5, '.', '' );
		}

		return implode( ' ', $out );
	}

	/**
	 * Rens hele områdelisten.
	 *
	 * @param mixed $areas Raw areas.
	 * @return array
	 */
	private function sanitize_areas( $areas ) {
		if ( ! is_array( $areas ) ) {
			return array();
		}

		$clean = array();
		foreach ( array_slice( $areas, 0, self::MAX_AREAS ) as $area ) {
			if ( ! is_array( $area ) ) {
				continue;
			}

			$points = $this->sanitize_points( isset( $area['points'] ) ? $area['points'] : '' );
			if ( '' === $points ) {
				continue;
			}

			$shop_id = isset( $area['shop_id'] ) ? absint( $area['shop_id'] ) : 0;
			if ( $shop_id && 'butiksside' !== get_post_type( $shop_id ) ) {
				$shop_id = 0;
			}

			$clean[] = array(
				'label'   => isset( $area['label'] ) ? sanitize_text_field( $area['label'] ) : '',
				'shop_id' => $shop_id,
				'color'   => isset( $area['color'] ) ? $this->sanitize_color( $area['color'] ) : '',
				'points'  => $points,
			);
		}

		return $clean;
	}

	/**
	 * Omregn normaliserede punkter til billedets pixels (til SVG viewBox).
	 *
	 * @param string $points Normalized points.
	 * @param int    $width  Image width.
	 * @param int    $height Image height.
	 * @return string
	 */
	private function points_to_viewbox( $points, $width, $height ) {
		$out = array();
		foreach ( preg_split( '/\s+/', trim( (string) $points ) ) as $pair ) {
			$xy = explode( ',', $pair );
			if ( 2 !== count( $xy ) ) {
				continue;
			}
			$out[] = round( (float) $xy[0] * $width, 1 ) . ',' . round( (float) $xy[1] * $height, 1 );
		}
		return implode( ' ', $out );
	}

	/**
	 * AJAX: Save image
	 */
	public function ajax_save_svg() {
		check_ajax_referer( 'centershop_floorplan_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$url           = $attachment_id ? wp_get_attachment_url( $attachment_id ) : false;

		if ( ! $url ) {
			wp_send_json_error( 'Invalid attachment' );
		}

		$mime_type = get_post_mime_type( $attachment_id );
		if ( ! $mime_type || 0 !== strpos( $mime_type, 'image/' ) ) {
			wp_send_json_error( 'File must be an image' );
		}

		$size = $this->detect_image_size( $attachment_id );
		if ( ! $size ) {
			wp_send_json_error( 'Could not read image size' );
		}

		update_option( self::OPTION_SVG, esc_url_raw( $url ) );
		update_option( self::OPTION_SIZE, $size );

		wp_send_json_success(
			array(
				'url'  => $url,
				'size' => $size,
			)
		);
	}

	/**
	 * AJAX: Save areas
	 */
	public function ajax_save_areas() {
		check_ajax_referer( 'centershop_floorplan_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$raw   = isset( $_POST['areas'] ) ? wp_unslash( $_POST['areas'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Saneres i sanitize_areas().
		$areas = json_decode( $raw, true );

		if ( ! is_array( $areas ) ) {
			wp_send_json_error( 'Invalid data' );
		}

		$areas = $this->sanitize_areas( $areas );
		update_option( self::OPTION_NAME, $areas );

		wp_send_json_success( array( 'areas' => $areas ) );
	}

	/**
	 * AJAX: Get data
	 */
	public function ajax_get_data() {
		check_ajax_referer( 'centershop_floorplan_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		wp_send_json_success(
			array(
				'svg'   => get_option( self::OPTION_SVG, '' ),
				'size'  => $this->get_size(),
				'areas' => $this->get_areas(),
			)
		);
	}

	/**
	 * AJAX: Delete area
	 */
	public function ajax_delete_area() {
		check_ajax_referer( 'centershop_floorplan_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$index = isset( $_POST['index'] ) ? absint( $_POST['index'] ) : -1;
		$areas = $this->get_areas();

		if ( isset( $areas[ $index ] ) ) {
			array_splice( $areas, $index, 1 );
			update_option( self::OPTION_NAME, $areas );
			wp_send_json_success();
		}

		wp_send_json_error( 'Area not found' );
	}

	/**
	 * AJAX: Search shops (frontend)
	 */
	public function ajax_search_shops() {
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array() );
		}

		$on_map = array();
		foreach ( $this->get_areas() as $area ) {
			if ( ! empty( $area['shop_id'] ) ) {
				$on_map[ (int) $area['shop_id'] ] = true;
			}
		}

		$shops = get_posts(
			array(
				'post_type'      => 'butiksside',
				'posts_per_page' => 10,
				's'              => $search,
				'post_status'    => 'publish',
			)
		);

		$results = array();
		foreach ( $shops as $shop ) {
			$results[] = array(
				'id'     => $shop->ID,
				'title'  => $shop->post_title,
				'url'    => get_permalink( $shop->ID ),
				'on_map' => isset( $on_map[ $shop->ID ] ),
			);
		}

		wp_send_json_success( $results );
	}

	/**
	 * Allow SVG uploads (kun administratorer).
	 *
	 * @param array $mimes Allowed mime types.
	 * @return array
	 */
	public function allow_svg_upload( $mimes ) {
		if ( current_user_can( 'manage_options' ) ) {
			$mimes['svg']  = 'image/svg+xml';
			$mimes['svgz'] = 'image/svg+xml';
		}
		return $mimes;
	}

	/**
	 * Fix SVG mime type detection (kun administratorer).
	 *
	 * @param array  $data     File data.
	 * @param string $file     File path.
	 * @param string $filename File name.
	 * @param array  $mimes    Mimes.
	 * @return array
	 */
	public function fix_svg_mime_type( $data, $file, $filename, $mimes ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $data;
		}

		$ext = isset( $data['ext'] ) ? $data['ext'] : '';

		if ( strlen( $ext ) < 1 ) {
			$exploded = explode( '.', $filename );
			$ext      = strtolower( end( $exploded ) );
		}

		if ( 'svg' === $ext || 'svgz' === $ext ) {
			$data['type'] = 'image/svg+xml';
			$data['ext']  = $ext;
		}

		return $data;
	}

	/**
	 * Samme markup som butiksvisningen: logo, kontaktboks og åbningstider.
	 *
	 * @param int $shop_id Shop post ID.
	 * @return string
	 */
	private function render_shop_view_html( $shop_id ) {
		$shop = get_post( absint( $shop_id ) );
		if ( ! $shop instanceof WP_Post ) {
			return '';
		}

		$previous         = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$GLOBALS['post']  = $shop;
		$post             = $shop;
		setup_postdata( $shop );

		ob_start();
		the_post_thumbnail(
			'header-image',
			array(
				'class' => 'wp-header-image',
				'alt'   => get_the_title(),
			)
		);
		include dirname( __DIR__ ) . '/templates/vis-butikinfo.php';
		include dirname( __DIR__ ) . '/templates/vis-aabningstider.php';
		$html = ob_get_clean();

		if ( $previous instanceof WP_Post ) {
			$GLOBALS['post'] = $previous;
			setup_postdata( $previous );
		} else {
			wp_reset_postdata();
		}

		return is_string( $html ) ? $html : '';
	}

	/**
	 * Butiksdata til popup. Samme visning som butikssiden.
	 *
	 * @param int $shop_id Shop post ID.
	 * @return array
	 */
	private function get_shop_card( $shop_id ) {
		$shop_id = absint( $shop_id );
		$name    = get_post_meta( $shop_id, 'butik_payed_name', true );
		if ( ! is_string( $name ) || '' === $name ) {
			$name = get_the_title( $shop_id );
		}

		return array(
			'name' => $name,
			'html' => $this->render_shop_view_html( $shop_id ),
		);
	}

	/**
	 * Floorplan shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function floorplan_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'shop_id'     => '',
				'show_search' => 'yes',
				'height'      => '600px',
			),
			$atts,
			'centershop_floorplan'
		);

		wp_enqueue_style( 'centershop-floorplan' );
		wp_enqueue_script( 'centershop-floorplan' );

		$svg_url = get_option( self::OPTION_SVG, '' );
		$size    = $this->get_size();
		$areas   = $this->get_areas();

		if ( empty( $svg_url ) || $size['width'] < 1 || $size['height'] < 1 ) {
			return '<p>' . esc_html__( 'Grundplan er ikke uploadet endnu.', 'centershop_txtdomain' ) . '</p>';
		}

		$selected_shop = absint( $atts['shop_id'] );
		$height        = preg_match( '/^\d+(\.\d+)?(px|vh|rem|em|%)$/', $atts['height'] ) ? $atts['height'] : '600px';
		$popup_id      = wp_unique_id( 'floorplan-popup-' );
		$popup_title   = wp_unique_id( 'floorplan-popup-title-' );

		$shop_cards = array();
		foreach ( $areas as $area ) {
			$shop_id = ! empty( $area['shop_id'] ) ? absint( $area['shop_id'] ) : 0;
			if ( $shop_id && 'publish' === get_post_status( $shop_id ) && ! isset( $shop_cards[ $shop_id ] ) ) {
				$shop_cards[ $shop_id ] = $this->get_shop_card( $shop_id );
			}
		}

		$shop_json = wp_json_encode(
			$shop_cards,
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		if ( ! is_string( $shop_json ) ) {
			$shop_json = '{}';
		}

		ob_start();
		?>
		<div class="centershop-floorplan-wrapper" data-selected-shop="<?php echo esc_attr( $selected_shop ? $selected_shop : '' ); ?>">
			<?php if ( 'yes' === $atts['show_search'] ) : ?>
				<div class="floorplan-search">
					<?php $search_id = wp_unique_id( 'floorplan-search-' ); ?>
					<label class="screen-reader-text" for="<?php echo esc_attr( $search_id ); ?>">
						<?php esc_html_e( 'Søg efter butik...', 'centershop_txtdomain' ); ?>
					</label>
					<input type="search"
						id="<?php echo esc_attr( $search_id ); ?>"
						class="floorplan-search-input"
						placeholder="<?php esc_attr_e( 'Søg efter butik...', 'centershop_txtdomain' ); ?>"
						autocomplete="off">
					<div class="floorplan-search-results" role="listbox"></div>
				</div>
			<?php endif; ?>

			<div class="floorplan-display">
				<svg class="floorplan-svg"
					xmlns="http://www.w3.org/2000/svg"
					viewBox="0 0 <?php echo esc_attr( $size['width'] . ' ' . $size['height'] ); ?>"
					style="max-height: <?php echo esc_attr( $height ); ?>;"
					role="group"
					aria-label="<?php esc_attr_e( 'Grundplan', 'centershop_txtdomain' ); ?>">
					<image href="<?php echo esc_url( $svg_url ); ?>"
						x="0" y="0"
						width="<?php echo esc_attr( $size['width'] ); ?>"
						height="<?php echo esc_attr( $size['height'] ); ?>"></image>
					<?php
					foreach ( $areas as $area ) {
						$shop_id   = ! empty( $area['shop_id'] ) ? absint( $area['shop_id'] ) : 0;
						$has_shop  = isset( $shop_cards[ $shop_id ] );
						$shop_name = $has_shop ? $shop_cards[ $shop_id ]['name'] : '';
						$color     = ! empty( $area['color'] ) ? $this->sanitize_color( $area['color'] ) : '';
						$classes   = 'floorplan-area-front ' . ( $has_shop ? 'has-shop' : 'no-shop' );
						if ( $has_shop && $selected_shop === $shop_id ) {
							$classes .= ' selected';
						}
						$points = $this->points_to_viewbox( isset( $area['points'] ) ? $area['points'] : '', $size['width'], $size['height'] );
						?>
						<polygon
							class="<?php echo esc_attr( $classes ); ?>"
							points="<?php echo esc_attr( $points ); ?>"
							<?php if ( $color ) : ?>
								style="--area-color: <?php echo esc_attr( $color ); ?>;"
							<?php endif; ?>
							<?php if ( $has_shop ) : ?>
								data-shop-id="<?php echo esc_attr( $shop_id ); ?>"
								data-shop-name="<?php echo esc_attr( $shop_name ); ?>"
								tabindex="0"
								role="button"
								aria-haspopup="dialog"
								aria-controls="<?php echo esc_attr( $popup_id ); ?>"
								aria-label="<?php echo esc_attr( $shop_name ); ?>"
							<?php else : ?>
								aria-hidden="true"
							<?php endif; ?>
						></polygon>
						<?php
					}
					?>
				</svg>
				<div class="floorplan-tooltip" hidden></div>
			</div>

			<dialog class="floorplan-popup" id="<?php echo esc_attr( $popup_id ); ?>" aria-labelledby="<?php echo esc_attr( $popup_title ); ?>">
				<button type="button" class="floorplan-popup__close" aria-label="<?php esc_attr_e( 'Luk', 'centershop_txtdomain' ); ?>">
					<span aria-hidden="true">&times;</span>
				</button>
				<div class="floorplan-popup__body"></div>
			</dialog>
			<script type="application/json" class="floorplan-shop-data"><?php echo $shop_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_* via wp_json_encode. ?></script>
		</div>
		<?php
		return ob_get_clean();
	}
}
