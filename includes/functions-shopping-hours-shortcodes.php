<?php
/**
 * Shortcodes for Shopping Hours functionality
 * Integrated from shoppinghours plugin into centershop
 */

class CenterShop_Shopping_Hours_Shortcodes 
{
    private $day_of_week;
    private $is_holliday;

    // Use consistent ASCII-safe day names
    private $week_days = ['mandag', 'tirsdag', 'onsdag', 'torsdag', 'fredag', 'lordag', 'sondag'];
    private $week_days_display = ['Mandag', 'Tirsdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lørdag', 'Søndag'];

    function __construct() {
        // Use modern date() instead of deprecated strftime()
        $day_number = date('N') - 1; // 0 = Monday, 6 = Sunday
        $this->day_of_week = $this->week_days[$day_number]; 
      
        $this->is_holliday = $this->check_for_holliday();

        $this->add_shortcodes();
    }

    public function add_shortcodes() {
        add_shortcode ('shoppingweek', array($this, 'show_shopping_hour_week'));
        add_shortcode ('collected-shoppingweek', array($this, 'show_collected_shopping_hour_week'));
        add_shortcode ('shoppingtoday', array($this, 'show_shopping_hours_today'));
        add_shortcode ('shopping-extra-text', array($this, 'show_shopping_exstra_text'));
    }

    public function show_shopping_hour_week() {
        $returning = '<div class="centeraabningstider ugeskema"><table style="width:100%;border-collapse:collapse;margin:0;background:transparent;border:none;">';
        
        for ($i = 0; $i < 7; $i++) {
            $week_day = $this->week_days[$i];
            $week_day_display = $this->week_days_display[$i];
            $class = ($week_day == $this->day_of_week) ? 'idag' : '';
            
            $returning .= '<tr class="ugedag '. $class .'" style="background:transparent;border:none;border-bottom:1px solid #ddd;">';
            $returning .= '<td class="ugedag-navn" style="padding:10px 15px;border:none;background:transparent;font-weight:600;">'.$week_day_display.'</td>';
            
            if ($this->is_holliday) {
                if (!$this->is_holliday[3]) {
                    $openhollyday = get_option($this->is_holliday[1], '');
                    $closehollyday = get_option($this->is_holliday[2], '');
                    $returning .= '<td class="openhours holliday" style="padding:10px 15px;border:none;background:transparent;text-align:center;">' . esc_html($openhollyday) . '</td>';
                    $returning .= '<td class="closinghours holliday" style="padding:10px 15px;border:none;background:transparent;text-align:center;">' . esc_html($closehollyday) . '</td>';
                } else {
                    $returning .= '<td colspan="2" class="closed-holliday" style="padding:10px 15px;border:none;background:transparent;">Der er lukket ' . esc_html($this->is_holliday[0] ?? '') . '</td>';          
                }
            } else {
                if (get_option($week_day.'_heltlukket')) {
                    $returning .= '<td colspan="2" class="lukket" style="padding:10px 15px;border:none;background:transparent;text-align:center;font-style:italic;">Lukket</td>';
                } else {
                    $openhour = get_option($week_day.'_aaben', '');
                    $closehour = get_option($week_day.'_lukket', '');
                    $returning .= '<td class="openhours" style="padding:10px 15px;border:none;background:transparent;text-align:center;">' . esc_html($openhour) . '</td>';
                    $returning .= '<td class="closinghours" style="padding:10px 15px;border:none;background:transparent;text-align:center;">' . esc_html($closehour) . '</td>';
                }          
            }
            $returning .= '</tr>';
        }
        
        $returning .= '</table></div>';
        return $returning;
    }

    public function show_collected_shopping_hour_week() {
        // Group days with identical opening hours
        $days_groups = [];
        $processed = [];
        
        for ($i = 0; $i < 7; $i++) {
            if (isset($processed[$i])) continue;
            
            $day = $this->week_days[$i];
            $aaben = get_option($day.'_aaben', '');
            $lukket = get_option($day.'_lukket', '');
            $heltlukket = get_option($day.'_heltlukket', false);
            
            $group = [
                'days' => [$this->week_days_display[$i]],
                'aaben' => $aaben,
                'lukket' => $lukket,
                'heltlukket' => $heltlukket
            ];
            
            // Find consecutive days with same hours
            for ($j = $i + 1; $j < 7; $j++) {
                if (isset($processed[$j])) continue;
                
                $compare_day = $this->week_days[$j];
                $compare_aaben = get_option($compare_day.'_aaben', '');
                $compare_lukket = get_option($compare_day.'_lukket', '');
                $compare_heltlukket = get_option($compare_day.'_heltlukket', false);
                
                if ($aaben === $compare_aaben && $lukket === $compare_lukket && $heltlukket === $compare_heltlukket) {
                    $group['days'][] = $this->week_days_display[$j];
                    $processed[$j] = true;
                } else {
                    break; // Stop at first non-matching day
                }
            }
            
            $days_groups[] = $group;
            $processed[$i] = true;
        }
        
        $returning = '<div class="centeraabningstider opsamlet" style="display:flex;flex-direction:column;gap:10px;background:transparent;padding:0;">';
        foreach ($days_groups as $group) {
            $returning .= '<div class="days-together-shooping-hours" style="font-weight:600;">';
            $returning .= esc_html(implode(' - ', $group['days']));
            $returning .= '</div>';
            
            if ($group['heltlukket']) {
                $returning .= '<div class="hours-for-days-together-shopping-hours" style="margin-top:3px;">Lukket</div>';
            } elseif (!empty($group['aaben']) && !empty($group['lukket'])) {
                $returning .= '<div class="hours-for-days-together-shopping-hours" style="margin-top:3px;">' . esc_html($group['aaben']) . ' - ' . esc_html($group['lukket']) . '</div>';
            } else {
                $returning .= '<div class="hours-for-days-together-shopping-hours" style="margin-top:3px;">Lukket</div>';
            }
        }
        $returning .= '</div>';

        return $returning;
    }

    public function show_shopping_hours_today() {
        $day = $this->day_of_week;
        $heltlukket = get_option($day.'_heltlukket', false);
        $aaben = get_option($day.'_aaben', '');
        $lukket = get_option($day.'_lukket', '');
        
        $returning = '<div class="todays-shopping-hours" style="background:transparent;padding:0;">';
        $returning .= '<p class="shopping-hour-title" style="font-size:1.2rem;font-weight:bold;margin-bottom:10px;">Åbningstid i dag</p>';
        
        // Check if completely closed
        if ($heltlukket) {
            $returning .= '<span class="shopping-hour-closed-today" style="font-size:1.2rem;">Lukket</span>';
        } elseif (!empty($aaben) && !empty($lukket)) {
            // Split time into hours and minutes
            $aaben_parts = explode(':', $aaben);
            $lukket_parts = explode(':', $lukket);
            
            if (count($aaben_parts) === 2 && count($lukket_parts) === 2) {
                $returning .= '<span class="shopping-hour-open-hours">' . esc_html($aaben_parts[0]) . '</span>';
                $returning .= '<span class="shopping-hour-open-minutes">' . esc_html($aaben_parts[1]) . '</span>';
                $returning .= '<span class="shopping-hour-hiphen"> - </span>';
                $returning .= '<span class="shopping-hour-closed-hours">' . esc_html($lukket_parts[0]) . '</span>';
                $returning .= '<span class="shopping-hour-closed-minutes">' . esc_html($lukket_parts[1]) . '</span>';
            } else {
                $returning .= '<span class="shopping-hour-time">' . esc_html($aaben) . ' - ' . esc_html($lukket) . '</span>';
            }
        } else {
            $returning .= '<span class="shopping-hour-closed-today">Lukket</span>';
        }
        
        $returning .= '</div>';
        return $returning;
    }

    public function show_shopping_exstra_text() {
        $extra_text = get_option('centershop_ekstra_tekst', '');
        if (empty($extra_text)) {
            return '';
        }
        
        $returning = '<div class="shopping-hour-extra-text">';
        $returning .= wp_kses_post($extra_text);
        $returning .= '</div>';

        return $returning;
    }

    private function check_for_holliday() {
        $is_holliday = false;
        $today = date('d-m-Y');
        $openhours = new CenterShop_Shopping_Hours();
        $helligdage = $openhours->shoppinghours_beregn_danske_helligdage();
        foreach ($helligdage as $helligdag) {
          if ($today == $helligdag[1]) {
            $aaben = $openhours->clean($helligdag[1]).'-aaben';
            $lukket = $openhours->clean($helligdag[1]).'-lukket';
            $heltlukket = $openhours->clean($helligdag[0]).'-heltlukket';
            $is_holliday = array ( $helligdag[1], get_option($aaben), get_option($lukket), get_option($heltlukket) );
          }
        }
        return $is_holliday;
    } 

}
