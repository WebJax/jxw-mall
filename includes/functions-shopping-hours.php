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
          <p>Åbningstider er blevet gemt.</p>
        </div>
      <?php endif; ?>
      
      <div class="centershop-admin-grid">
        <div class="shoppinghours-settings">
          <form method="post" action="options.php">
          <?php settings_fields( 'shoppinghours-group' ); ?>
          <?php do_settings_sections( 'shoppinghours-group' ); ?>
          <table class="form-table shoppinghours">
            <tr>
              <th>Ugedag</th>
              <th>Åbner</th>
              <th>Lukker</th>
              <th>Helt lukket</th>
            </tr>
            <?php
            $weekdays = [
              'mandag' => 'Mandag',
              'tirsdag' => 'Tirsdag', 
              'onsdag' => 'Onsdag',
              'torsdag' => 'Torsdag',
              'fredag' => 'Fredag',
              'lordag' => 'Lørdag',
              'sondag' => 'Søndag'
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
          </table>
          <h2 class="hellig-overskrift">EKSTRA TEKST</h2>
          <table class="form-table shoppinghours">
            <tr valign="top">
              <td><textarea name="centershop_ekstra_tekst" rows="4" cols="101" placeholder="F.eks: 'Bemærk ændrede åbningstider i sommerferien'"><?php echo esc_textarea( get_option('centershop_ekstra_tekst', '') ); ?></textarea></td>
            </tr>
          </table>
          <h2 class="hellig-overskrift">HELLIGDAGE</h2>
          <table class="form-table shoppinghours">
            <tr>
              <th>Helligdag</th>
              <th>Dato</th>
              <th>Åbner</th>
              <th>Lukker</th>
              <th>Helt lukket</th>
            </tr>
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
          </table>
          <?php submit_button('Gem Åbningstider', 'primary large'); ?>
          </form>
        </div>
        
        <div class="shoppinghours-demo">
          <h2>Eksempel Visning</h2>
          <p class="description">Sådan ser åbningstiderne ud på hjemmesiden</p>
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
    wp_enqueue_style( 'centershop_shoppinghours_frontend_css', plugin_dir_url( dirname(__FILE__) ) . 'css/shopping-hours-styles.css', false, filemtime(plugin_dir_path( __FILE__ ) . '../css/shopping-hours-styles.css') );
  } 

  public function shoppinghours_load_admin_scripts() {
    wp_enqueue_style( 'centershop_shoppinghours_admin_css', plugin_dir_url( dirname(__FILE__) ) . 'css/shopping-hours-styles.css', false, filemtime(plugin_dir_path( __FILE__ ) . '../css/shopping-hours-styles.css') );
    wp_enqueue_script( 'centershop_shoppinghours_admin_script', plugin_dir_url( dirname(__FILE__) ) . 'js/shopping-hours-scripts.js', array('jquery'), filemtime(plugin_dir_path( __FILE__ ) . '../js/shopping-hours-scripts.js'), true );
    wp_localize_script( 'centershop_shoppinghours_admin_script', 'shoppinghours_ajax_url', admin_url( 'admin-ajax.php' ) );
  }  

  /**
   * AJAX functions
   **/
  public function shoppinghours_register_custom_closing_day() {
    $registered = "registered";
    echo $registered;
    wp_die();
  }

  public function shoppinghours_deregister_custom_closing_day() {
    $deregistered = "deregistering";
    echo $deregistered;
    wp_die();
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

  public function clean($string) {
     $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
     $string = preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.

     return preg_replace('/-+/', '-', $string); // Replaces multiple hyphens with single one.
  }

}
