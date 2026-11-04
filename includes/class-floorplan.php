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
	const OPTION_NAME    = 'centershop_floorplan_data';
	const OPTION_SVG     = 'centershop_floorplan_svg';
	const OPTION_SIZE    = 'centershop_floorplan_size';
	const OPTION_MARKERS = 'centershop_floorplan_markers';

	/**
	 * Maksimalt antal områder, punkter og symboler (beskytter mod misbrug).
	 */
	const MAX_AREAS   = 500;
	const MAX_POINTS  = 200;
	const MAX_MARKERS = 200;

	/**
	 * Standardstørrelser for symboler på kortet (px).
	 */
	const MARKER_CIRCLE_MIN     = 28;
	const MARKER_CIRCLE_MAX     = 120;
	const MARKER_CIRCLE_DEFAULT = 44;
	const MARKER_ICON_MIN       = 12;
	const MARKER_ICON_MAX       = 96;
	const MARKER_ICON_DEFAULT   = 20;
	const MARKER_BG_FACILITY    = '#1d2327';
	const MARKER_BG_ENTRANCE    = '#c2410c';

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
		add_action( 'wp_ajax_check_floorplan_marker_icon', array( $this, 'ajax_check_marker_icon' ) );
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
				'markers' => $this->get_markers(),
				'icons'   => $this->get_marker_icons_for_js(),
				'markerLimits' => array(
					'circleMin'     => self::MARKER_CIRCLE_MIN,
					'circleMax'     => self::MARKER_CIRCLE_MAX,
					'circleDefault' => self::MARKER_CIRCLE_DEFAULT,
					'iconMin'       => self::MARKER_ICON_MIN,
					'iconMax'       => self::MARKER_ICON_MAX,
					'iconDefault'   => self::MARKER_ICON_DEFAULT,
					'facilityBg'    => self::MARKER_BG_FACILITY,
					'entranceBg'    => self::MARKER_BG_ENTRANCE,
				),
				'shops'   => $shops,
				'strings' => array(
					'selectShop'          => __( 'Vælg butik', 'centershop_txtdomain' ),
					'noShop'              => __( 'Ingen butik', 'centershop_txtdomain' ),
					'deleteArea'          => __( 'Slet område', 'centershop_txtdomain' ),
					'confirmDelete'       => __( 'Er du sikker på at du vil slette dette område?', 'centershop_txtdomain' ),
					'saved'               => __( 'Grundplan gemt!', 'centershop_txtdomain' ),
					'unsaved'             => __( 'Der er ændringer, som ikke er gemt.', 'centershop_txtdomain' ),
					'error'               => __( 'Der opstod en fejl', 'centershop_txtdomain' ),
					'chooseImage'         => __( 'Vælg grundplan', 'centershop_txtdomain' ),
					'useImage'            => __( 'Brug dette billede', 'centershop_txtdomain' ),
					'noAreas'             => __( 'Ingen områder defineret endnu.', 'centershop_txtdomain' ),
					'areaLabel'           => __( 'Områdenavn', 'centershop_txtdomain' ),
					'areaShop'            => __( 'Tilknyt butik', 'centershop_txtdomain' ),
					'areaColor'           => __( 'Farve', 'centershop_txtdomain' ),
					'newArea'             => __( 'Lokale', 'centershop_txtdomain' ),
					'selectHint'          => __( 'Klik på et område i grundplanen for at redigere det. Træk i hjørnerne for at justere. Dobbeltklik på en kant for at tilføje et hjørne, og på et hjørne for at fjerne det. Klik på et symbol for at redigere det.', 'centershop_txtdomain' ),
					'drawHint'            => __( 'Klik for at sætte hjørner. Klik på det første punkt (eller tryk Enter) for at afslutte. Backspace fjerner sidste punkt, Esc annullerer.', 'centershop_txtdomain' ),
					'importConfirm'       => __( 'Der findes allerede områder. OK = erstat dem, Annuller = tilføj de importerede.', 'centershop_txtdomain' ),
					'importDone'          => __( 'områder importeret. Husk at gemme.', 'centershop_txtdomain' ),
					'importInvalid'       => __( 'Filen har ikke det forventede format.', 'centershop_txtdomain' ),
					'noMarkers'           => __( 'Ingen symboler endnu.', 'centershop_txtdomain' ),
					'placeHint'           => __( 'Klik på grundplanen der, hvor symbolet skal stå. Esc annullerer.', 'centershop_txtdomain' ),
					'facility'            => __( 'Facilitet', 'centershop_txtdomain' ),
					'entrance'            => __( 'Indgang', 'centershop_txtdomain' ),
					'kindLabel'           => __( 'Type', 'centershop_txtdomain' ),
					'markerLabel'         => __( 'Overskrift', 'centershop_txtdomain' ),
					'markerIcon'          => __( 'Ikon', 'centershop_txtdomain' ),
					'markerNumber'        => __( 'Indgangsnummer', 'centershop_txtdomain' ),
					'markerNumberHelp'    => __( 'Vises på kortet. Med ikonet Nummer er tallet selve symbolet.', 'centershop_txtdomain' ),
					'markerDescription'   => __( 'Beskrivelse', 'centershop_txtdomain' ),
					'deleteMarker'        => __( 'Slet symbol', 'centershop_txtdomain' ),
					'confirmDeleteMarker' => __( 'Er du sikker på at du vil slette dette symbol?', 'centershop_txtdomain' ),
					'dragMarker'          => __( 'Træk symbolet på grundplanen for at flytte det.', 'centershop_txtdomain' ),
					'chooseIcon'          => __( 'Vælg symbol', 'centershop_txtdomain' ),
					'useIcon'             => __( 'Brug dette symbol', 'centershop_txtdomain' ),
					'uploadIcon'          => __( 'Upload symbol', 'centershop_txtdomain' ),
					'removeIcon'          => __( 'Fjern symbol', 'centershop_txtdomain' ),
					'iconHelp'            => __( 'SVG, PNG eller WebP med gennemsigtig baggrund.', 'centershop_txtdomain' ),
					'iconInvalid'         => __( 'Symbolet skal være en SVG, PNG eller WebP med gennemsigtig baggrund.', 'centershop_txtdomain' ),
					'markerBg'            => __( 'Baggrundsfarve', 'centershop_txtdomain' ),
					'circleSize'          => __( 'Cirkelstørrelse', 'centershop_txtdomain' ),
					'iconSize'            => __( 'Symbolstørrelse', 'centershop_txtdomain' ),
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
								<div id="fp-marker-layer" class="floorplan-marker-layer"></div>
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
							<h2 class="hndle"><?php esc_html_e( 'Faciliteter og indgange', 'centershop_txtdomain' ); ?></h2>
							<div class="inside">
								<div id="markers-list"></div>
								<p>
									<button type="button" class="button" id="add-marker-btn" <?php disabled( ! $ready ); ?>>
										<span class="dashicons dashicons-location"></span>
										<?php esc_html_e( 'Placér symbol', 'centershop_txtdomain' ); ?>
									</button>
									<button type="button" class="button" id="cancel-marker-btn" hidden>
										<?php esc_html_e( 'Annullér', 'centershop_txtdomain' ); ?>
									</button>
								</p>
								<p class="description">
									<?php esc_html_e( 'Toiletter, information, indgangsnumre og andre symboler på kortet.', 'centershop_txtdomain' ); ?>
								</p>
							</div>
						</div>

						<div class="postbox">
							<h2 class="hndle"><?php esc_html_e( 'Detaljer', 'centershop_txtdomain' ); ?></h2>
							<div class="inside">
								<div id="area-editor"></div>
							</div>
						</div>
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
	 * Gemte symboler (faciliteter og indgange).
	 *
	 * Ligger i en egen option, så den eksisterende områdeliste ikke skifter form.
	 * Ikon-URL tilføjes her og gemmes ikke, så den altid peger på det aktuelle medie.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_markers() {
		$markers = get_option( self::OPTION_MARKERS, array() );
		if ( ! is_array( $markers ) ) {
			return array();
		}

		$out = array();
		foreach ( array_values( $markers ) as $marker ) {
			if ( is_array( $marker ) ) {
				$out[] = $this->hydrate_marker( $marker );
			}
		}
		return $out;
	}

	/**
	 * Udfyld visningsfelter (URL, standardstørrelse og farve) uden at skrive dem til databasen.
	 *
	 * @param array<string, mixed> $marker Stored marker.
	 * @return array<string, mixed>
	 */
	private function hydrate_marker( $marker ) {
		$kind = ( isset( $marker['kind'] ) && 'entrance' === $marker['kind'] ) ? 'entrance' : 'facility';
		$icon_id = isset( $marker['icon_id'] ) ? absint( $marker['icon_id'] ) : 0;
		$url     = '';
		if ( $icon_id && $this->marker_icon_is_allowed( $icon_id, false ) ) {
			$resolved = wp_get_attachment_url( $icon_id );
			$url      = is_string( $resolved ) ? $resolved : '';
		}
		if ( '' === $url ) {
			$icon_id = 0;
		}

		$bg = isset( $marker['bg'] ) ? $this->sanitize_color( $marker['bg'] ) : '';
		if ( '' === $bg ) {
			$bg = 'entrance' === $kind ? self::MARKER_BG_ENTRANCE : self::MARKER_BG_FACILITY;
		}

		$marker['kind']        = $kind;
		$marker['icon_id']     = $icon_id;
		$marker['icon_url']    = $url;
		$marker['bg']          = $bg;
		$marker['circle_size'] = $this->clamp_marker_size(
			isset( $marker['circle_size'] ) ? $marker['circle_size'] : self::MARKER_CIRCLE_DEFAULT,
			self::MARKER_CIRCLE_MIN,
			self::MARKER_CIRCLE_MAX,
			self::MARKER_CIRCLE_DEFAULT
		);
		$marker['icon_size']   = $this->clamp_marker_size(
			isset( $marker['icon_size'] ) ? $marker['icon_size'] : self::MARKER_ICON_DEFAULT,
			self::MARKER_ICON_MIN,
			self::MARKER_ICON_MAX,
			self::MARKER_ICON_DEFAULT
		);

		return $marker;
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
	 * Tilladte ikoner til faciliteter og indgange.
	 *
	 * `body` er fast SVG-markup uden brugerdata.
	 *
	 * @return array<string, array{label:string, body:string}>
	 */
	private function marker_icons() {
		return array(
			'number'    => array(
				'label' => __( 'Nummer', 'centershop_txtdomain' ),
				'body'  => '<text x="12" y="16.5" text-anchor="middle" font-size="12" font-weight="700" fill="currentColor" stroke="none" font-family="system-ui,sans-serif">1</text>',
			),
			'door'      => array(
				'label' => __( 'Dør', 'centershop_txtdomain' ),
				'body'  => '<path d="M6 20V4h9v16"/><path d="M15 4.2c3 2.2 3 13.4 0 15.6"/><circle cx="8.6" cy="12" r="0.8" fill="currentColor" stroke="none"/>',
			),
			'toilet'    => array(
				'label' => __( 'Toilet', 'centershop_txtdomain' ),
				'body'  => '<text x="12" y="16.5" text-anchor="middle" font-size="9" font-weight="700" fill="currentColor" stroke="none" font-family="system-ui,sans-serif">WC</text>',
			),
			'info'      => array(
				'label' => __( 'Information', 'centershop_txtdomain' ),
				'body'  => '<circle cx="12" cy="7" r="1.2" fill="currentColor" stroke="none"/><path d="M12 11v7"/>',
			),
			'elevator'  => array(
				'label' => __( 'Elevator', 'centershop_txtdomain' ),
				'body'  => '<path d="M7 10l5-4 5 4"/><path d="M7 14l5 4 5-4"/>',
			),
			'stairs'    => array(
				'label' => __( 'Trapper', 'centershop_txtdomain' ),
				'body'  => '<path d="M4 20h5v-5h5v-5h6"/>',
			),
			'accessible' => array(
				'label' => __( 'Handicap', 'centershop_txtdomain' ),
				'body'  => '<circle cx="9" cy="5" r="1.5" fill="currentColor" stroke="none"/><circle cx="8" cy="16.5" r="3.2"/><path d="M9.2 8.2L7.5 12H5.5"/><path d="M9.2 8.2l4 1.2 1.6 3.6"/><path d="M12.8 9.5l2.4-1.5"/>',
			),
			'baby'      => array(
				'label' => __( 'Pusle', 'centershop_txtdomain' ),
				'body'  => '<circle cx="12" cy="6" r="2" fill="currentColor" stroke="none"/><path d="M8 11.5h8"/><path d="M9 11.5l-1 7h8l-1-7"/>',
			),
			'parking'   => array(
				'label' => __( 'Parkering', 'centershop_txtdomain' ),
				'body'  => '<text x="12" y="17" text-anchor="middle" font-size="14" font-weight="700" fill="currentColor" stroke="none" font-family="system-ui,sans-serif">P</text>',
			),
			'atm'       => array(
				'label' => __( 'Hæveautomat', 'centershop_txtdomain' ),
				'body'  => '<rect x="3.5" y="6" width="17" height="12" rx="2"/><path d="M3.5 10.5h17"/><path d="M7 14.5h4"/>',
			),
			'firstaid'  => array(
				'label' => __( 'Førstehjælp', 'centershop_txtdomain' ),
				'body'  => '<path d="M12 5v14M5 12h14"/>',
			),
			'wifi'      => array(
				'label' => __( 'Wifi', 'centershop_txtdomain' ),
				'body'  => '<path d="M4.5 9.5a11 11 0 0 1 15 0"/><path d="M7.5 13a7 7 0 0 1 9 0"/><path d="M10.5 16.2a3 3 0 0 1 3 0"/><circle cx="12" cy="19" r="1" fill="currentColor" stroke="none"/>',
			),
			'lockers'   => array(
				'label' => __( 'Garderobe', 'centershop_txtdomain' ),
				'body'  => '<rect x="3.5" y="3.5" width="7" height="17" rx="1"/><rect x="13.5" y="3.5" width="7" height="17" rx="1"/><circle cx="8.8" cy="12" r="0.7" fill="currentColor" stroke="none"/><circle cx="16.2" cy="12" r="0.7" fill="currentColor" stroke="none"/>',
			),
		);
	}

	/**
	 * Ikoner til admin-editoren.
	 *
	 * @return array<int, array{id:string, label:string, svg:string}>
	 */
	private function get_marker_icons_for_js() {
		$out = array();
		foreach ( $this->marker_icons() as $id => $icon ) {
			$out[] = array(
				'id'    => $id,
				'label' => $icon['label'],
				'svg'   => $this->wrap_marker_svg( $icon['body'] ),
			);
		}
		return $out;
	}

	/**
	 * Pak ikon-stier ind i et fast SVG-element.
	 *
	 * @param string $body Indre markup fra marker_icons().
	 * @return string
	 */
	private function wrap_marker_svg( $body ) {
		return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $body . '</svg>';
	}

	/**
	 * SVG for et kendt ikon. Ukendt id falder tilbage til information.
	 *
	 * @param string $icon Icon id.
	 * @return string
	 */
	private function marker_svg( $icon ) {
		$icons = $this->marker_icons();
		if ( ! isset( $icons[ $icon ] ) ) {
			$icon = 'info';
		}
		return $this->wrap_marker_svg( $icons[ $icon ]['body'] );
	}

	/**
	 * Normaliseret koordinat 0-1, eller null hvis ugyldig.
	 *
	 * @param mixed $value Raw value.
	 * @return float|null
	 */
	private function sanitize_unit( $value ) {
		if ( is_string( $value ) ) {
			$value = trim( $value );
			if ( ! preg_match( '/^\d*\.?\d+$/', $value ) ) {
				return null;
			}
		} elseif ( ! is_int( $value ) && ! is_float( $value ) ) {
			return null;
		}

		$number = (float) $value;
		if ( $number < 0 || $number > 1 ) {
			return null;
		}

		return $number;
	}

	/**
	 * Afkort en streng uden at flække multibyte-tegn.
	 *
	 * @param string $value Text.
	 * @param int    $limit Max characters.
	 * @return string
	 */
	private function limit_text( $value, $limit ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $limit );
		}
		return substr( $value, 0, $limit );
	}

	/**
	 * Rens symboler til faciliteter og indgange.
	 *
	 * @param mixed $markers Raw markers.
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_markers( $markers ) {
		if ( ! is_array( $markers ) ) {
			return array();
		}

		$allowed = array_keys( $this->marker_icons() );
		$clean   = array();

		foreach ( array_slice( $markers, 0, self::MAX_MARKERS ) as $marker ) {
			if ( ! is_array( $marker ) || ! isset( $marker['x'], $marker['y'] ) ) {
				continue;
			}

			$x = $this->sanitize_unit( $marker['x'] );
			$y = $this->sanitize_unit( $marker['y'] );
			if ( null === $x || null === $y ) {
				continue;
			}

			$kind = ( isset( $marker['kind'] ) && 'entrance' === $marker['kind'] ) ? 'entrance' : 'facility';
			$icon = isset( $marker['icon'] ) ? sanitize_key( $marker['icon'] ) : '';
			if ( ! in_array( $icon, $allowed, true ) ) {
				$icon = 'entrance' === $kind ? 'number' : 'info';
			}

			$number = isset( $marker['number'] ) ? sanitize_text_field( $marker['number'] ) : '';
			$number = $this->limit_text( $number, 6 );

			$label = isset( $marker['label'] ) ? sanitize_text_field( $marker['label'] ) : '';
			$label = $this->limit_text( $label, 80 );

			$description = isset( $marker['description'] ) ? sanitize_textarea_field( $marker['description'] ) : '';
			$description = $this->limit_text( $description, 2000 );

			$icon_id = isset( $marker['icon_id'] ) ? absint( $marker['icon_id'] ) : 0;
			if ( $icon_id && ! $this->marker_icon_is_allowed( $icon_id, true ) ) {
				$icon_id = 0;
			}

			$bg = isset( $marker['bg'] ) ? $this->sanitize_color( $marker['bg'] ) : '';
			if ( '' === $bg ) {
				$bg = 'entrance' === $kind ? self::MARKER_BG_ENTRANCE : self::MARKER_BG_FACILITY;
			}

			$clean[] = array(
				'kind'        => $kind,
				'label'       => $label,
				'description' => $description,
				'icon'        => $icon,
				'icon_id'     => $icon_id,
				'number'      => 'entrance' === $kind ? $number : '',
				'bg'          => $bg,
				'circle_size' => $this->clamp_marker_size(
					isset( $marker['circle_size'] ) ? $marker['circle_size'] : self::MARKER_CIRCLE_DEFAULT,
					self::MARKER_CIRCLE_MIN,
					self::MARKER_CIRCLE_MAX,
					self::MARKER_CIRCLE_DEFAULT
				),
				'icon_size'   => $this->clamp_marker_size(
					isset( $marker['icon_size'] ) ? $marker['icon_size'] : self::MARKER_ICON_DEFAULT,
					self::MARKER_ICON_MIN,
					self::MARKER_ICON_MAX,
					self::MARKER_ICON_DEFAULT
				),
				'x'           => number_format( $x, 5, '.', '' ),
				'y'           => number_format( $y, 5, '.', '' ),
			);
		}

		return $clean;
	}

	/**
	 * Hold en størrelse inden for de tilladte pixelgrænser.
	 *
	 * @param mixed $value    Raw value.
	 * @param int   $min      Minimum.
	 * @param int   $max      Maximum.
	 * @param int   $fallback Used when the value is not numeric.
	 * @return int
	 */
	private function clamp_marker_size( $value, $min, $max, $fallback ) {
		if ( is_string( $value ) && ! preg_match( '/^\d+$/', trim( $value ) ) ) {
			return $fallback;
		}
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}
		$size = (int) $value;
		return min( $max, max( $min, $size ) );
	}

	/**
	 * Tilladte mime-typer for egne symboler.
	 *
	 * @return string[]
	 */
	private function marker_icon_mimes() {
		return array( 'image/png', 'image/webp', 'image/svg+xml' );
	}

	/**
	 * Om et medie kan bruges som symbol.
	 *
	 * Gennemsigtighed tjekkes ved upload og gem. Ved visning nøjes vi med mime-typen,
	 * så forsiden ikke læser filen ved hvert sidevisning.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $require_alpha Kræv gennemsigtig baggrund for PNG og WebP.
	 * @return bool
	 */
	private function marker_icon_is_allowed( $attachment_id, $require_alpha ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return false;
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( ! is_string( $mime ) || ! in_array( $mime, $this->marker_icon_mimes(), true ) ) {
			return false;
		}

		$file = get_attached_file( $attachment_id );
		if ( ! is_string( $file ) || ! is_readable( $file ) ) {
			return false;
		}

		if ( 'image/svg+xml' === $mime ) {
			$head = file_get_contents( $file, false, null, 0, 800 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return is_string( $head ) && (bool) preg_match( '/<svg[\s>]/i', $head );
		}

		if ( ! $require_alpha ) {
			return true;
		}

		return $this->raster_has_alpha( $file, $mime );
	}

	/**
	 * PNG eller WebP med alfakanal.
	 *
	 * @param string $path File path.
	 * @param string $mime Mime type.
	 * @return bool
	 */
	private function raster_has_alpha( $path, $mime ) {
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return false;
		}

		$has_alpha = 'image/webp' === $mime ? $this->webp_has_alpha( $handle ) : $this->png_has_alpha( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $has_alpha;
	}

	/**
	 * @param resource $handle Open file handle.
	 * @return bool
	 */
	private function png_has_alpha( $handle ) {
		$signature = fread( $handle, 8 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		if ( "\x89PNG\r\n\x1a\n" !== $signature ) {
			return false;
		}

		while ( ! feof( $handle ) ) {
			$header = fread( $handle, 8 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			if ( ! is_string( $header ) || 8 !== strlen( $header ) ) {
				return false;
			}
			$length = unpack( 'N', substr( $header, 0, 4 ) );
			$type   = substr( $header, 4, 4 );
			$size   = is_array( $length ) ? (int) $length[1] : 0;
			if ( $size < 0 ) {
				return false;
			}

			if ( 'IHDR' === $type ) {
				$ihdr = fread( $handle, 13 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
				fread( $handle, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
				if ( ! is_string( $ihdr ) || strlen( $ihdr ) < 10 ) {
					return false;
				}
				$color = ord( $ihdr[9] );
				if ( 4 === $color || 6 === $color ) {
					return true;
				}
				continue;
			}

			if ( 'tRNS' === $type ) {
				return true;
			}

			if ( 'IDAT' === $type || 'IEND' === $type || $size > 1000000 ) {
				return false;
			}

			if ( 0 !== fseek( $handle, $size + 4, SEEK_CUR ) ) {
				return false;
			}
		}

		return false;
	}

	/**
	 * @param resource $handle Open file handle.
	 * @return bool
	 */
	private function webp_has_alpha( $handle ) {
		$header = fread( $handle, 12 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		if ( ! is_string( $header ) || 12 !== strlen( $header ) || 'RIFF' !== substr( $header, 0, 4 ) || 'WEBP' !== substr( $header, 8, 4 ) ) {
			return false;
		}

		while ( ! feof( $handle ) ) {
			$chunk = fread( $handle, 8 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			if ( ! is_string( $chunk ) || 8 !== strlen( $chunk ) ) {
				return false;
			}
			$length = unpack( 'V', substr( $chunk, 4, 4 ) );
			$type   = substr( $chunk, 0, 4 );
			$size   = is_array( $length ) ? (int) $length[1] : 0;
			if ( $size < 0 ) {
				return false;
			}

			if ( 'VP8X' === $type ) {
				$flags = fread( $handle, 1 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
				return is_string( $flags ) && '' !== $flags && ( 0x10 === ( ord( $flags ) & 0x10 ) );
			}

			if ( 'ALPH' === $type ) {
				return true;
			}

			if ( 'VP8L' === $type ) {
				$payload = fread( $handle, 5 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
				// Bit 36 i VP8L-headeren er alpha_is_used.
				return is_string( $payload ) && 5 === strlen( $payload ) && 0x2f === ord( $payload[0] ) && ( 0x10 === ( ord( $payload[4] ) & 0x10 ) );
			}

			if ( 'VP8 ' === $type ) {
				return false;
			}

			$skip = $size + ( $size & 1 );
			if ( $size > 1000000 || 0 !== fseek( $handle, $skip, SEEK_CUR ) ) {
				return false;
			}
		}

		return false;
	}

	/**
	 * Overskrift der vises ved hover og i modalen.
	 *
	 * @param array<string, string> $marker Marker.
	 * @return string
	 */
	private function marker_public_label( $marker ) {
		$label = isset( $marker['label'] ) ? trim( (string) $marker['label'] ) : '';
		if ( '' !== $label ) {
			return $label;
		}

		if ( isset( $marker['kind'] ) && 'entrance' === $marker['kind'] ) {
			$number = isset( $marker['number'] ) ? (string) $marker['number'] : '';
			if ( '' !== $number ) {
				return sprintf(
					/* translators: %s: entrance number */
					__( 'Indgang %s', 'centershop_txtdomain' ),
					$number
				);
			}
			return __( 'Indgang', 'centershop_txtdomain' );
		}

		return __( 'Facilitet', 'centershop_txtdomain' );
	}

	/**
	 * Indhold af et kort-symbol (ikon, nummer eller badge).
	 *
	 * @param array<string, string> $marker Marker.
	 * @return string
	 */
	private function marker_glyph_html( $marker ) {
		$kind   = isset( $marker['kind'] ) ? (string) $marker['kind'] : 'facility';
		$icon   = isset( $marker['icon'] ) ? (string) $marker['icon'] : 'info';
		$number = isset( $marker['number'] ) ? (string) $marker['number'] : '';
		$url    = isset( $marker['icon_url'] ) ? (string) $marker['icon_url'] : '';

		if ( '' !== $url ) {
			$html = '<span class="floorplan-marker__glyph"><img src="' . esc_url( $url ) . '" alt=""></span>';
			if ( 'entrance' === $kind && '' !== $number ) {
				$html .= '<span class="floorplan-marker__badge">' . esc_html( $number ) . '</span>';
			}
			return $html;
		}

		if ( 'number' === $icon ) {
			if ( '' === $number ) {
				$number = 'entrance' === $kind ? '–' : '1';
			}
			return '<span class="floorplan-marker__num">' . esc_html( $number ) . '</span>';
		}

		$html = '<span class="floorplan-marker__glyph">' . $this->marker_svg( $icon ) . '</span>';
		if ( 'entrance' === $kind && '' !== $number ) {
			$html .= '<span class="floorplan-marker__badge">' . esc_html( $number ) . '</span>';
		}

		return $html;
	}

	/**
	 * Inline-styling for placering, cirkel, ikon og baggrund.
	 *
	 * @param array<string, mixed> $marker Marker.
	 * @return string
	 */
	private function marker_style( $marker ) {
		$x      = isset( $marker['x'] ) ? (float) $marker['x'] : 0;
		$y      = isset( $marker['y'] ) ? (float) $marker['y'] : 0;
		$circle = isset( $marker['circle_size'] ) ? (int) $marker['circle_size'] : self::MARKER_CIRCLE_DEFAULT;
		$icon   = isset( $marker['icon_size'] ) ? (int) $marker['icon_size'] : self::MARKER_ICON_DEFAULT;
		$bg     = isset( $marker['bg'] ) ? $this->sanitize_color( $marker['bg'] ) : '';
		if ( '' === $bg ) {
			$bg = self::MARKER_BG_FACILITY;
		}

		return sprintf(
			'left:%s%%;top:%s%%;--marker-size:%dpx;--marker-icon:%dpx;--marker-bg:%s',
			number_format( $x * 100, 3, '.', '' ),
			number_format( $y * 100, 3, '.', '' ),
			$circle,
			$icon,
			$bg
		);
	}

	/**
	 * Modal-indhold for et symbol. Genbruger den eksisterende popup.
	 *
	 * @param array<string, string> $marker Marker.
	 * @param string                $label  Public label.
	 * @return string
	 */
	private function marker_popup_html( $marker, $label ) {
		$html = '<h2 class="floorplan-popup__title">' . esc_html( $label ) . '</h2>';
		$description = isset( $marker['description'] ) ? trim( (string) $marker['description'] ) : '';
		if ( '' !== $description ) {
			$html .= '<div class="floorplan-popup__text">' . nl2br( esc_html( $description ), false ) . '</div>';
		}
		return $html;
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

		$markers = null;
		if ( isset( $_POST['markers'] ) ) {
			$raw_markers = wp_unslash( $_POST['markers'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Saneres i sanitize_markers().
			$markers     = json_decode( $raw_markers, true );
			if ( ! is_array( $markers ) ) {
				wp_send_json_error( 'Invalid data' );
			}
		}

		$areas = $this->sanitize_areas( $areas );
		update_option( self::OPTION_NAME, $areas );

		if ( is_array( $markers ) ) {
			update_option( self::OPTION_MARKERS, $this->sanitize_markers( $markers ) );
		}
		$markers = $this->get_markers();

		wp_send_json_success(
			array(
				'areas'   => $areas,
				'markers' => $markers,
			)
		);
	}

	/**
	 * AJAX: Tjek at et uploadet symbol er SVG, PNG eller WebP med gennemsigtig baggrund.
	 */
	public function ajax_check_marker_icon() {
		check_ajax_referer( 'centershop_floorplan_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Du har ikke adgang til at gøre det.', 'centershop_txtdomain' ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( ! $this->marker_icon_is_allowed( $attachment_id, true ) ) {
			wp_send_json_error( __( 'Symbolet skal være en SVG, PNG eller WebP med gennemsigtig baggrund.', 'centershop_txtdomain' ) );
		}

		$url = wp_get_attachment_url( $attachment_id );
		if ( ! is_string( $url ) || '' === $url ) {
			wp_send_json_error( __( 'Symbolet skal være en SVG, PNG eller WebP med gennemsigtig baggrund.', 'centershop_txtdomain' ) );
		}

		wp_send_json_success(
			array(
				'id'  => $attachment_id,
				'url' => $url,
			)
		);
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
				'svg'     => get_option( self::OPTION_SVG, '' ),
				'size'    => $this->get_size(),
				'areas'   => $this->get_areas(),
				'markers' => $this->get_markers(),
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
		$markers = $this->get_markers();

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

		$marker_cards = array();
		foreach ( $markers as $marker ) {
			$public_label   = $this->marker_public_label( $marker );
			$marker_cards[] = array(
				'label' => $public_label,
				'html'  => $this->marker_popup_html( $marker, $public_label ),
			);
		}

		$marker_json = wp_json_encode(
			$marker_cards,
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		if ( ! is_string( $marker_json ) ) {
			$marker_json = '[]';
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
				<?php if ( $markers ) : ?>
					<div class="floorplan-marker-layer">
						<?php
						foreach ( $markers as $index => $marker ) {
							$public_label = $this->marker_public_label( $marker );
							$icon         = isset( $marker['icon'] ) ? (string) $marker['icon'] : '';
							$kind         = isset( $marker['kind'] ) ? (string) $marker['kind'] : 'facility';
							$custom       = ! empty( $marker['icon_url'] );
							$classes      = 'floorplan-marker';
							if ( 'entrance' === $kind ) {
								$classes .= ' is-entrance';
							}
							if ( 'number' === $icon && ! $custom ) {
								$classes .= ' is-number';
							}
							?>
							<button type="button"
								class="<?php echo esc_attr( $classes ); ?>"
								style="<?php echo esc_attr( $this->marker_style( $marker ) ); ?>"
								data-marker-index="<?php echo esc_attr( (string) $index ); ?>"
								data-label="<?php echo esc_attr( $public_label ); ?>"
								aria-label="<?php echo esc_attr( $public_label ); ?>"
								aria-haspopup="dialog"
								aria-controls="<?php echo esc_attr( $popup_id ); ?>">
								<?php echo $this->marker_glyph_html( $marker ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Tal og URL escapes. Indbygget SVG er fast markup. ?>
							</button>
							<?php
						}
						?>
					</div>
				<?php endif; ?>
				<div class="floorplan-tooltip" hidden></div>
			</div>

			<dialog class="floorplan-popup" id="<?php echo esc_attr( $popup_id ); ?>" aria-labelledby="<?php echo esc_attr( $popup_title ); ?>">
				<button type="button" class="floorplan-popup__close" aria-label="<?php esc_attr_e( 'Luk', 'centershop_txtdomain' ); ?>">
					<span aria-hidden="true">&times;</span>
				</button>
				<div class="floorplan-popup__body"></div>
			</dialog>
			<script type="application/json" class="floorplan-shop-data"><?php echo $shop_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_* via wp_json_encode. ?></script>
			<?php if ( $markers ) : ?>
				<script type="application/json" class="floorplan-marker-data"><?php echo $marker_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_* via wp_json_encode. ?></script>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
