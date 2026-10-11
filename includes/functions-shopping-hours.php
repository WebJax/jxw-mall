<?php
/**
 * Shopping Hours functionality
 * Integrated from shoppinghours plugin into centershop
 */



class CenterShop_Shopping_Hours {
  
  function __construct() {
    // Nothing to see here
  }
  
  /**
   * Options page created
   **/
  public function shoppinghours_setup_settings_page() { ?>
    <div class="wrap centershop-opening-hours-wrap">
      <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

      <?php if (isset($_GET['settings-updated'])) : ?>
        <div class="notice notice-success is-dismissible">
          <p><?php esc_html_e('Åbningstider er blevet gemt.', 'centershop_txtdomain'); ?></p>
        </div>
      <?php endif; ?>

      <p class="description">
        <?php esc_html_e('Angiv centrets generelle åbningstider herunder. Individuelle butikker kan vælge at arve disse tider eller angive egne. Brug hjælpefanen øverst til højre for yderligere vejledning.', 'centershop_txtdomain'); ?>
      </p>

      <div class="centershop-admin-grid">
        <div class="shoppinghours-settings">
          <form method="post" action="options.php">
          <?php settings_fields( 'shoppinghours-group' ); ?>
          <?php do_settings_sections( 'shoppinghours-group' ); ?>
          <table class="form-table shoppinghours">
            <thead>
              <tr>
                <th><?php esc_html_e('Ugedag', 'centershop_txtdomain'); ?></th>
                <th><?php esc_html_e('Åbner', 'centershop_txtdomain'); ?>
                  <span class="dashicons dashicons-editor-help centershop-field-tip"
                        title="<?php esc_attr_e('Tidspunkt centret åbner, f.eks. 10:00', 'centershop_txtdomain'); ?>"></span>
                </th>
                <th><?php esc_html_e('Lukker', 'centershop_txtdomain'); ?>
                  <span class="dashicons dashicons-editor-help centershop-field-tip"
                        title="<?php esc_attr_e('Tidspunkt centret lukker, f.eks. 18:00', 'centershop_txtdomain'); ?>"></span>
                </th>
                <th><?php esc_html_e('Helt lukket', 'centershop_txtdomain'); ?>
                  <span class="dashicons dashicons-editor-help centershop-field-tip"
                        title="<?php esc_attr_e('Afkryds hvis centret er lukket hele dagen', 'centershop_txtdomain'); ?>"></span>
                </th>
              </tr>
            </thead>
            <tbody>
            <?php
            $weekdays = [
              'mandag' => __('Mandag', 'centershop_txtdomain'),
              'tirsdag' => __('Tirsdag', 'centershop_txtdomain'),
              'onsdag' => __('Onsdag', 'centershop_txtdomain'),
              'torsdag' => __('Torsdag', 'centershop_txtdomain'),
              'fredag' => __('Fredag', 'centershop_txtdomain'),
              'lordag' => __('Lørdag', 'centershop_txtdomain'),
              'sondag' => __('Søndag', 'centershop_txtdomain'),
            ];

            foreach ($weekdays as $day_key => $day_label) {
              $aaben = get_option($day_key.'_aaben', '');
              $lukket = get_option($day_key.'_lukket', '');
              $heltlukket = get_option($day_key.'_heltlukket', false);
            ?>
            <tr valign="top" class="centershop-weekday-row">
              <td scope="row"><strong><?php echo esc_html($day_label); ?></strong></td>
              <td><input type="time" name="<?php echo esc_attr($day_key); ?>_aaben" value="<?php echo esc_attr($aaben); ?>" class="centershop-time-input" /></td>
              <td><input type="time" name="<?php echo esc_attr($day_key); ?>_lukket" value="<?php echo esc_attr($lukket); ?>" class="centershop-time-input" /></td>
              <td align="center"><input type="checkbox" id="<?php echo esc_attr($day_key); ?>_heltlukket" name="<?php echo esc_attr($day_key); ?>_heltlukket" value="1" <?php checked($heltlukket, true); ?> class="centershop-closed-toggle" /></td>
            </tr>
            <?php } ?>
            </tbody>
          </table>

          <h2 class="hellig-overskrift"><?php esc_html_e('Ekstra tekst', 'centershop_txtdomain'); ?></h2>
          <p class="description"><?php esc_html_e('Valgfri besked der vises under åbningstiderne på hjemmesiden, f.eks. ved ændrede tider i ferier eller på helligdage.', 'centershop_txtdomain'); ?></p>
          <table class="form-table shoppinghours">
            <tr valign="top">
              <td>
                <textarea name="centershop_ekstra_tekst"
                          id="centershop_ekstra_tekst"
                          rows="4" cols="101"
                          placeholder="<?php esc_attr_e('F.eks: Bemærk ændrede åbningstider i sommerferien', 'centershop_txtdomain'); ?>"
                          aria-describedby="centershop_ekstra_tekst_desc"><?php echo esc_textarea( get_option('centershop_ekstra_tekst', '') ); ?></textarea>
              </td>
            </tr>
          </table>

          <?php $this->render_ekstra_datoer_section(); ?>

          <h2 class="hellig-overskrift"><?php esc_html_e('Helligdage', 'centershop_txtdomain'); ?></h2>
          <p class="description"><?php esc_html_e('Åbningstider for de danske helligdage. Datoerne beregnes automatisk for indeværende år.', 'centershop_txtdomain'); ?></p>
          <table class="form-table shoppinghours">
            <thead>
              <tr>
                <th><?php esc_html_e('Helligdag', 'centershop_txtdomain'); ?></th>
                <th><?php esc_html_e('Dato', 'centershop_txtdomain'); ?></th>
                <th><?php esc_html_e('Åbner', 'centershop_txtdomain'); ?></th>
                <th><?php esc_html_e('Lukker', 'centershop_txtdomain'); ?></th>
                <th><?php esc_html_e('Helt lukket', 'centershop_txtdomain'); ?></th>
              </tr>
            </thead>
            <tbody>
               <?php
              $allehelligdage = $this->shoppinghours_beregn_danske_helligdage();
              foreach ($allehelligdage as $helligdag) {
                $helligdag_aaben = $this->clean($helligdag[0]).'_aaben';
                $helligdag_lukket = $this->clean($helligdag[0]).'_lukket';
                $helligdag_heltlukket = $this->clean($helligdag[0]).'_heltlukket';

                $aaben = get_option($helligdag_aaben, '');
                $lukket = get_option($helligdag_lukket, '');
                $heltlukket = get_option($helligdag_heltlukket, false);
                ?>
                <tr valign="top">
                  <td><strong><?php echo esc_html($helligdag[0]); ?></strong></td>
                  <td><?php echo esc_html($helligdag[1]); ?></td>
                  <td><input type="time" name="<?php echo esc_attr($helligdag_aaben); ?>" value="<?php echo esc_attr($aaben); ?>" class="centershop-time-input" /></td>
                  <td><input type="time" name="<?php echo esc_attr($helligdag_lukket); ?>" value="<?php echo esc_attr($lukket); ?>" class="centershop-time-input" /></td>
                  <td align="center"><input type="checkbox" id="<?php echo esc_attr($helligdag_heltlukket); ?>" name="<?php echo esc_attr($helligdag_heltlukket); ?>" value="1" <?php checked($heltlukket, true); ?> class="centershop-closed-toggle" /></td>
                </tr>
                <?php
              }?>
            </tbody>
          </table>
          <?php submit_button(__('Gem åbningstider', 'centershop_txtdomain'), 'primary large'); ?>
          </form>
        </div>

        <div class="shoppinghours-demo">
          <h2><?php esc_html_e('Eksempelvisning', 'centershop_txtdomain'); ?></h2>
          <p class="description"><?php esc_html_e('Sådan ser åbningstiderne ud på hjemmesiden.', 'centershop_txtdomain'); ?></p>
          <div class="shoppinghours-container">
            <?php include(plugin_dir_path(__FILE__) . '../templates/vis-center-aabningstider.php'); ?>
          </div>
        </div>
      </div>
    </div>
  <?php
  }

  public function admin_init() {
    require_once(plugin_dir_path(__FILE__) . 'functions-shopping-hours-shortcodes.php');
    $shopping_hours_shortcodes = new CenterShop_Shopping_Hours_Shortcodes();

    // Migrate old data format to new format (only runs once)
    $this->migrate_old_data();

    if ( is_admin() ){ // admin actions
      add_action( 'admin_menu', array($this, 'shoppinghours_add_menu') );
      add_action( 'admin_init', array($this, 'shoppinghours_register_settings') );
      add_action( 'wp_ajax_shoppinghours_register_custom_closing_day', array($this, 'shoppinghours_register_custom_closing_day') );
      add_action( 'wp_ajax_shoppinghours_deregister_custom_closing_day', array($this, 'shoppinghours_deregister_custom_closing_day') );
    }
    add_action( 'admin_enqueue_scripts', array($this, 'shoppinghours_load_admin_scripts') );
    add_action( 'wp_enqueue_scripts', array($this, 'shoppinghours_load_frontend_scripts') );
  }
  
  /**
   * Migrate data from old format (with hyphens) to new format (with underscores)
   * Only runs once when migration hasn't been done yet
   */
  private function migrate_old_data() {
    // Check if migration has already been done
    if (get_option('centershop_opening_hours_migrated')) {
      return;
    }
    
    $weekdays = ['mandag', 'tirsdag', 'onsdag', 'torsdag', 'fredag', 'lordag', 'sondag'];
    
    // Migrate weekday data
    foreach ($weekdays as $day) {
      $old_aaben = get_option($day.'-aaben');
      $old_lukket = get_option($day.'-lukket');
      $old_heltlukket = get_option($day.'-heltlukket');
      
      if ($old_aaben !== false) {
        update_option($day.'_aaben', $old_aaben);
      }
      if ($old_lukket !== false) {
        update_option($day.'_lukket', $old_lukket);
      }
      if ($old_heltlukket !== false) {
        update_option($day.'_heltlukket', $old_heltlukket);
      }
    }
    
    // Migrate extra text
    $old_extra_text = get_option('ekstra-tekst');
    if ($old_extra_text !== false) {
      update_option('centershop_ekstra_tekst', $old_extra_text);
    }
    
    // Migrate holiday data
    $allehelligdage = $this->shoppinghours_beregn_danske_helligdage();
    foreach ($allehelligdage as $helligdag) {
      $holiday_key = $this->clean($helligdag[0]);
      
      $old_aaben = get_option($holiday_key.'-aaben');
      $old_lukket = get_option($holiday_key.'-lukket');
      $old_heltlukket = get_option($holiday_key.'-heltlukket');
      
      if ($old_aaben !== false) {
        update_option($holiday_key.'_aaben', $old_aaben);
      }
      if ($old_lukket !== false) {
        update_option($holiday_key.'_lukket', $old_lukket);
      }
      if ($old_heltlukket !== false) {
        update_option($holiday_key.'_heltlukket', $old_heltlukket);
      }
    }
    
    // Mark migration as complete
    update_option('centershop_opening_hours_migrated', true);
  }

  public function shoppinghours_register_settings() { // whitelist options
    $weekdays = ['mandag', 'tirsdag', 'onsdag', 'torsdag', 'fredag', 'lordag', 'sondag'];
    
    foreach ($weekdays as $day) {
      // Register with underscore (new format)
      register_setting( 'shoppinghours-group', $day.'_aaben' );
      register_setting( 'shoppinghours-group', $day.'_lukket' );
      register_setting( 'shoppinghours-group', $day.'_heltlukket' );
    }

    register_setting( 'shoppinghours-group', 'centershop_ekstra_tekst' );
    register_setting( 'shoppinghours-group', 'centershop_ekstra_datoer', array(
      'type' => 'array',
      'sanitize_callback' => array( $this, 'sanitize_ekstra_datoer' ),
      'default' => array(),
    ) );
    
    $allehelligdage = $this->shoppinghours_beregn_danske_helligdage();
    foreach ($allehelligdage as $helligdag) {
      $helligdag_aaben = $this->clean($helligdag[0]).'_aaben';
      $helligdag_lukket = $this->clean($helligdag[0]).'_lukket';
      $helligdag_heltlukket = $this->clean($helligdag[0]).'_heltlukket';
      register_setting( 'shoppinghours-group', $helligdag_aaben );
      register_setting( 'shoppinghours-group', $helligdag_lukket );
      register_setting( 'shoppinghours-group', $helligdag_heltlukket );
    }
  }

  public function shoppinghours_add_menu() {
    // Menu now handled in class-admin-menu.php
    // Keep this method for the callback reference
  }

  public function shoppinghours_load_frontend_scripts() {
    wp_enqueue_style( 'centershop_shoppinghours_frontend_css', plugin_dir_url( dirname(__FILE__) ) . 'css/shopping-hours-styles.css', array(), filemtime(plugin_dir_path( __FILE__ ) . '../css/shopping-hours-styles.css') );
  } 

  public function shoppinghours_load_admin_scripts() {
    wp_enqueue_style( 'centershop_shoppinghours_admin_css', plugin_dir_url( dirname(__FILE__) ) . 'css/shopping-hours-styles.css', array(), filemtime(plugin_dir_path( __FILE__ ) . '../css/shopping-hours-styles.css') );
    wp_enqueue_script( 'centershop_shoppinghours_admin_script', plugin_dir_url( dirname(__FILE__) ) . 'js/shopping-hours-scripts.js', array('jquery'), filemtime(plugin_dir_path( __FILE__ ) . '../js/shopping-hours-scripts.js'), true );
    wp_localize_script( 'centershop_shoppinghours_admin_script', 'shoppinghours_ajax_data', array(
      'url' => admin_url( 'admin-ajax.php' ),
      'weekdays' => array( 'Søndag', 'Mandag', 'Tirsdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lørdag' ),
    ) );
  }  

  /**
   * AJAX functions
   **/
  public function shoppinghours_register_custom_closing_day() {
    check_ajax_referer( 'centershop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( array( 'message' => __( 'Adgang nægtet.', 'centershop_txtdomain' ) ), 403 );
    }
    wp_send_json_success( array( 'message' => 'registered' ) );
  }

  public function shoppinghours_deregister_custom_closing_day() {
    check_ajax_referer( 'centershop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( array( 'message' => __( 'Adgang nægtet.', 'centershop_txtdomain' ) ), 403 );
    }
    wp_send_json_success( array( 'message' => 'deregistering' ) );
  }

  /**
   * Beregn helligdage
   **/
  public function shoppinghours_beregn_danske_helligdage() {
    $X = date("Y");
    $A = $X % 19;
    $B = intval($X/100);
    $C = $X % 100;
    $D = intval($B/4);
    $E = $B % 4;
    $F = intval(($B+8)/25);
    $G = intval(($B-$F+1)/3);
    $H = (19*$A+$B-$D-$G+15)%30;
    $J = intval($C/4);
    $K = $C%4;
    $L = (32+2*$E+2*$J-$H-$K)%7;
    $M = intval(($A+11*$H+22*$L)/451);
    $N = intval(($H+$L-7*$M+114)/31);
    $P = ($H+$L-7*$M+114)%31;
    $Q = ($N-3)*31+$P-20;
    $dag = $P+1;
    $maaned = $N;

    $udgangsdato = $X.'-'.$maaned.'-'.$dag;

    $helligdage = array (
      'nytaarsdag'            => array( 'Nytårsdag', date('d-m-Y', strtotime($X. '-01-01' )) ),
      'skaertorsdag'          => array( 'Skærtorsdag', date('d-m-Y', strtotime($udgangsdato. ' - 2 days')) ),
      'langfredag'            => array( 'Langfredag', date('d-m-Y', strtotime($udgangsdato. ' - 1 days')) ),
      'paaskedag'             => array( 'Påskedag', date('d-m-Y', strtotime($udgangsdato)) ),
      'andenpaaskedag'        => array( '2. Påskedag', date('d-m-Y', strtotime($udgangsdato. ' + 1 days')) ),
      'storebededag'          => array( 'Store Bededag', date('d-m-Y', strtotime($udgangsdato. ' + 26 days')) ),
      'kristihimmelfartsdag'  => array( 'Kristi Himmefartsdag', date('d-m-Y', strtotime($udgangsdato. ' + 39 days')) ),
      'pinsedag'              => array( 'Pinsedag', date('d-m-Y', strtotime($udgangsdato. ' + 49 days')) ),
      'andenpinsedag'         => array( '2. Pinsedag', date('d-m-Y', strtotime($udgangsdato. ' + 50 days')) ),
      'grundlovsdag'          => array( 'Grundlovsdag', date('d-m-Y', strtotime($X. '-06-05' )) ),
      'juleaften'             => array( 'Juleaften', date('d-m-Y', strtotime($X. '-12-24' )) ),
      'forstejuledag'         => array( '1. Juledag', date('d-m-Y', strtotime($X. '-12-25' )) ),
      'andenjuledag'          => array( '2. Juledag', date('d-m-Y', strtotime($X. '-12-26' )) ),
      'nytaarsaftensdag'      => array( 'Nytårsaftensdag', date('d-m-Y', strtotime($X. '-12-31' )) ),
    ); 

    return $helligdage;
  }

  /**
   * Admin fields for single dates that replace the weekday hours.
   */
  private function render_ekstra_datoer_section() {
    $rows = $this->get_ekstra_datoer();
    ?>
    <h2 class="hellig-overskrift"><?php esc_html_e('Ekstra datoer', 'centershop_txtdomain'); ?></h2>
    <p class="description"><?php esc_html_e('Tilføj enkelte datoer med ændrede åbningstider. De overskriver den almindelige ugedag på den valgte dato.', 'centershop_txtdomain'); ?></p>
    <input type="hidden" name="centershop_ekstra_datoer[_present]" value="1" />
    <div class="centershop-ekstra-datoer-wrap">
      <table class="form-table shoppinghours centershop-ekstra-datoer" id="centershop-ekstra-datoer" data-next-index="<?php echo esc_attr( (string) count( $rows ) ); ?>">
        <thead>
          <tr>
            <th scope="col"><?php esc_html_e('Dato', 'centershop_txtdomain'); ?></th>
            <th scope="col"><?php esc_html_e('Ugedag', 'centershop_txtdomain'); ?></th>
            <th scope="col"><?php esc_html_e('Åbner', 'centershop_txtdomain'); ?></th>
            <th scope="col"><?php esc_html_e('Lukker', 'centershop_txtdomain'); ?></th>
            <th scope="col"><?php esc_html_e('Helt lukket', 'centershop_txtdomain'); ?></th>
            <th scope="col"><?php esc_html_e('Bemærkning', 'centershop_txtdomain'); ?></th>
            <th scope="col"><span class="screen-reader-text"><?php esc_html_e('Handling', 'centershop_txtdomain'); ?></span></th>
          </tr>
        </thead>
        <tbody id="centershop-ekstra-datoer-body">
          <?php foreach ( $rows as $index => $row ) : ?>
            <?php $this->render_ekstra_dato_row( (string) $index, $row ); ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p>
      <button type="button" class="button" id="centershop-add-ekstra-dato"><?php esc_html_e('Tilføj dato', 'centershop_txtdomain'); ?></button>
    </p>
    <template id="centershop-ekstra-dato-template">
      <?php $this->render_ekstra_dato_row( '__INDEX__', array() ); ?>
    </template>
    <?php
  }

  /**
   * One extra-date row. Index may be a numeric key or the JS placeholder.
   *
   * @param array{date?: string, label?: string, open?: string, close?: string, closed?: bool} $row
   */
  private function render_ekstra_dato_row( string $index, array $row ) {
    $date = isset( $row['date'] ) ? (string) $row['date'] : '';
    $label = isset( $row['label'] ) ? (string) $row['label'] : '';
    $open = isset( $row['open'] ) ? (string) $row['open'] : '';
    $close = isset( $row['close'] ) ? (string) $row['close'] : '';
    $closed = ! empty( $row['closed'] );
    $weekday = $date !== '' ? $this->weekday_label_for_date( $date ) : '';
    $is_past = $date !== '' && $date < current_time( 'Y-m-d' );
    $closed_id = 'centershop_ekstra_closed_' . $index;
    ?>
    <tr class="centershop-ekstra-dato-row<?php echo $is_past ? ' is-past' : ''; ?>">
      <td>
        <input type="date" class="centershop-ekstra-dato" name="centershop_ekstra_datoer[<?php echo esc_attr( $index ); ?>][date]" value="<?php echo esc_attr( $date ); ?>" aria-label="<?php esc_attr_e('Dato', 'centershop_txtdomain'); ?>" />
      </td>
      <td><span class="centershop-weekday-hint"><?php echo esc_html( $weekday ); ?></span></td>
      <td>
        <input type="time" class="centershop-time-input" name="centershop_ekstra_datoer[<?php echo esc_attr( $index ); ?>][open]" value="<?php echo esc_attr( $open ); ?>" aria-label="<?php esc_attr_e('Åbner', 'centershop_txtdomain'); ?>" <?php disabled( $closed ); ?> />
      </td>
      <td>
        <input type="time" class="centershop-time-input" name="centershop_ekstra_datoer[<?php echo esc_attr( $index ); ?>][close]" value="<?php echo esc_attr( $close ); ?>" aria-label="<?php esc_attr_e('Lukker', 'centershop_txtdomain'); ?>" <?php disabled( $closed ); ?> />
      </td>
      <td>
        <label class="screen-reader-text" for="<?php echo esc_attr( $closed_id ); ?>"><?php esc_html_e('Helt lukket', 'centershop_txtdomain'); ?></label>
        <input type="checkbox" id="<?php echo esc_attr( $closed_id ); ?>" class="centershop-closed-toggle" name="centershop_ekstra_datoer[<?php echo esc_attr( $index ); ?>][closed]" value="1" <?php checked( $closed ); ?> />
      </td>
      <td>
        <input type="text" class="centershop-ekstra-note" name="centershop_ekstra_datoer[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e('F.eks. Julefrokost', 'centershop_txtdomain'); ?>" aria-label="<?php esc_attr_e('Bemærkning', 'centershop_txtdomain'); ?>" />
      </td>
      <td>
        <button type="button" class="button button-small centershop-remove-ekstra-dato"><?php esc_html_e('Fjern', 'centershop_txtdomain'); ?></button>
      </td>
    </tr>
    <?php
  }

  /**
   * Stored extra dates, sorted by date.
   *
   * @return array<int, array{date: string, label: string, closed: bool, open: string, close: string}>
   */
  public function get_ekstra_datoer(): array {
    $stored = get_option( 'centershop_ekstra_datoer', array() );
    if ( ! is_array( $stored ) ) {
      return array();
    }

    $rows = array();
    foreach ( $stored as $row ) {
      if ( ! is_array( $row ) || empty( $row['date'] ) ) {
        continue;
      }
      $rows[] = array(
        'date' => (string) $row['date'],
        'label' => isset( $row['label'] ) ? (string) $row['label'] : '',
        'closed' => ! empty( $row['closed'] ),
        'open' => isset( $row['open'] ) ? (string) $row['open'] : '',
        'close' => isset( $row['close'] ) ? (string) $row['close'] : '',
      );
    }

    return $rows;
  }

  /**
   * Extra date that replaces the weekday, or null.
   *
   * @return array{date: string, label: string, closed: bool, open: string, close: string}|null
   */
  public function get_ekstra_dato_for( string $ymd ): ?array {
    foreach ( $this->get_ekstra_datoer() as $row ) {
      if ( $row['date'] === $ymd ) {
        return $row;
      }
    }
    return null;
  }

  /**
   * Hours for a calendar date. An extra date wins over the weekday.
   *
   * @return array{date: string, closed: bool, open: string, close: string, label: string, override: bool}
   */
  public function resolve_hours_for_date( string $ymd ): array {
    $override = $this->get_ekstra_dato_for( $ymd );
    if ( $override ) {
      return array(
        'date' => $ymd,
        'closed' => $override['closed'],
        'open' => $override['open'],
        'close' => $override['close'],
        'label' => $override['label'],
        'override' => true,
      );
    }

    $day = $this->weekday_key_for_date( $ymd );
    return array(
      'date' => $ymd,
      'closed' => (bool) get_option( $day . '_heltlukket', false ),
      'open' => (string) get_option( $day . '_aaben', '' ),
      'close' => (string) get_option( $day . '_lukket', '' ),
      'label' => '',
      'override' => false,
    );
  }

  /**
   * Monday-to-Sunday dates for the current week in the site timezone.
   *
   * @return string[] Y-m-d, index 0 is Monday.
   */
  public function current_week_dates(): array {
    $today = new DateTimeImmutable( 'now', wp_timezone() );
    $monday = $today->modify( '-' . ( (int) $today->format( 'N' ) - 1 ) . ' days' );
    $dates = array();
    for ( $i = 0; $i < 7; $i++ ) {
      $dates[] = $monday->modify( '+' . $i . ' days' )->format( 'Y-m-d' );
    }
    return $dates;
  }

  /**
   * Weekday option key for a Y-m-d date.
   */
  public function weekday_key_for_date( string $ymd ): string {
    $keys = array(
      1 => 'mandag',
      2 => 'tirsdag',
      3 => 'onsdag',
      4 => 'torsdag',
      5 => 'fredag',
      6 => 'lordag',
      7 => 'sondag',
    );
    $dt = DateTimeImmutable::createFromFormat( 'Y-m-d', $ymd );
    if ( ! $dt ) {
      return 'mandag';
    }
    return $keys[ (int) $dt->format( 'N' ) ] ?? 'mandag';
  }

  /**
   * Danish weekday name for a Y-m-d date.
   */
  public function weekday_label_for_date( string $ymd ): string {
    $labels = array(
      'mandag' => __( 'Mandag', 'centershop_txtdomain' ),
      'tirsdag' => __( 'Tirsdag', 'centershop_txtdomain' ),
      'onsdag' => __( 'Onsdag', 'centershop_txtdomain' ),
      'torsdag' => __( 'Torsdag', 'centershop_txtdomain' ),
      'fredag' => __( 'Fredag', 'centershop_txtdomain' ),
      'lordag' => __( 'Lørdag', 'centershop_txtdomain' ),
      'sondag' => __( 'Søndag', 'centershop_txtdomain' ),
    );
    $key = $this->weekday_key_for_date( $ymd );
    return $labels[ $key ] ?? '';
  }

  /**
   * Visitor-facing note for an extra date, e.g. "24. dec. · Julefrokost".
   *
   * @param array{date?: string, label?: string} $row
   */
  public function format_ekstra_dato_note( array $row ): string {
    $date = isset( $row['date'] ) ? (string) $row['date'] : '';
    $dt = $date !== '' ? DateTimeImmutable::createFromFormat( 'Y-m-d', $date ) : false;
    $date_label = $dt ? wp_date( 'j. M', $dt->getTimestamp() ) : '';
    $label = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';

    if ( $date_label !== '' && $label !== '' ) {
      return $date_label . ' · ' . $label;
    }
    return $date_label !== '' ? $date_label : $label;
  }

  /**
   * Keep valid extra dates. A hidden marker lets an empty list replace stored rows.
   *
   * @param mixed $input
   * @return array<int, array{date: string, label: string, closed: bool, open: string, close: string}>
   */
  public function sanitize_ekstra_datoer( $input ): array {
    if ( ! is_array( $input ) ) {
      return array();
    }

    $by_date = array();
    foreach ( $input as $key => $row ) {
      if ( $key === '_present' || ! is_array( $row ) ) {
        continue;
      }

      $date = sanitize_text_field( $row['date'] ?? '' );
      $dt = DateTimeImmutable::createFromFormat( 'Y-m-d', $date );
      if ( ! $dt || $dt->format( 'Y-m-d' ) !== $date ) {
        continue;
      }

      $closed = ! empty( $row['closed'] );
      $by_date[ $date ] = array(
        'date' => $date,
        'label' => mb_substr( sanitize_text_field( $row['label'] ?? '' ), 0, 80 ),
        'closed' => $closed,
        'open' => $closed ? '' : $this->sanitize_time( (string) ( $row['open'] ?? '' ) ),
        'close' => $closed ? '' : $this->sanitize_time( (string) ( $row['close'] ?? '' ) ),
      );
    }

    ksort( $by_date );
    return array_values( $by_date );
  }

  /**
   * Accept HH:MM from a time input.
   */
  private function sanitize_time( string $time ): string {
    $time = sanitize_text_field( $time );
    if ( ! preg_match( '/^(\d{2}):(\d{2})/', $time, $matches ) ) {
      return '';
    }
    $hour = (int) $matches[1];
    $minute = (int) $matches[2];
    if ( $hour > 23 || $minute > 59 ) {
      return '';
    }
    return sprintf( '%02d:%02d', $hour, $minute );
  }

  public function clean(string $string) {
     $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
     $string = preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.

     return preg_replace('/-+/', '-', $string); // Replaces multiple hyphens with single one.
  }

}

/**
 * Extra date that replaces the weekday, or null.
 *
 * @return array{date: string, label: string, closed: bool, open: string, close: string}|null
 */
function centershop_get_ekstra_dato( string $ymd ): ?array {
  $hours = new CenterShop_Shopping_Hours();
  return $hours->get_ekstra_dato_for( $ymd );
}

/**
 * All stored extra dates.
 *
 * @return array<int, array{date: string, label: string, closed: bool, open: string, close: string}>
 */
function centershop_get_ekstra_datoer(): array {
  $hours = new CenterShop_Shopping_Hours();
  return $hours->get_ekstra_datoer();
}

/**
 * Weekday option key (mandag … sondag) for a Y-m-d date.
 */
function centershop_weekday_key_for_date( string $ymd ): string {
  $hours = new CenterShop_Shopping_Hours();
  return $hours->weekday_key_for_date( $ymd );
}

/**
 * Current week as weekday key => Y-m-d.
 *
 * @return array<string, string>
 */
function centershop_current_week_dates_by_key(): array {
  $hours = new CenterShop_Shopping_Hours();
  $dates = $hours->current_week_dates();
  $keys = array( 'mandag', 'tirsdag', 'onsdag', 'torsdag', 'fredag', 'lordag', 'sondag' );
  return array_combine( $keys, $dates );
}

/**
 * Visitor-facing note for an extra date.
 *
 * @param array{date?: string, label?: string} $row
 */
function centershop_format_ekstra_dato_note( array $row ): string {
  $hours = new CenterShop_Shopping_Hours();
  return $hours->format_ekstra_dato_note( $row );
}
