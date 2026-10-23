<?php
/**
 * Shortcodes for Shopping Hours functionality
 * Integrated from shoppinghours plugin into centershop
 * 
 * @param array $atts The shortcode attributes
 * @return string The shopping hours
 */

class CenterShop_Shopping_Hours_Shortcodes 
{
    /**
     * The day of the week
     * @var string
     */
    private $day_of_week;
    /**
     * Whether it is a holiday
     * @var bool
     */
    private $is_holliday;

    /**
     * The days of the week in ASCII-safe format
     * @var array
     */
    private $week_days = ['mandag', 'tirsdag', 'onsdag', 'torsdag', 'fredag', 'lordag', 'sondag'];
    /**
     * The days of the week in display format
     * @var array
     */
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
        $hours_api = new CenterShop_Shopping_Hours();
        $week_dates = $hours_api->current_week_dates();
        $returning = '<div class="centeraabningstider ugeskema"><table>';
        
        for ($i = 0; $i < 7; $i++) {
            $week_day = $this->week_days[$i];
            $week_day_display = $this->week_days_display[$i];
            $class = ($week_day == $this->day_of_week) ? 'idag' : '';
            $date = $week_dates[$i] ?? '';
            $override = $date !== '' ? $hours_api->get_ekstra_dato_for($date) : null;
            if ($override) {
                $class .= ' ekstra-dato';
            }
            
            $returning .= '<tr class="ugedag '. trim($class) .'">';
            $returning .= '<td class="ugedag-navn">'. esc_html($week_day_display);
            if ($override) {
                $note = $hours_api->format_ekstra_dato_note($override);
                if ($note !== '') {
                    $returning .= '<span class="ekstra-dato-note">' . esc_html($note) . '</span>';
                }
            }
            $returning .= '</td>';
            
            if ($override) {
                $returning .= $this->render_hour_cells(!empty($override['closed']), $override['open'], $override['close']);
            } elseif ($this->is_holliday) {
                if (!is_array($this->is_holliday) || !$this->is_holliday[3]) {
                    $openhollyday = get_option($this->is_holliday[1] ?? '', '');
                    $closehollyday = get_option($this->is_holliday[2] ?? '', '');
                    $returning .= '<td class="openhours holliday">' . esc_html($openhollyday) . '</td>';
                    $returning .= '<td class="closinghours holliday">' . esc_html($closehollyday) . '</td>';
                } else {
                    $returning .= '<td colspan="2" class="closed-holliday">Der er lukket ' . esc_html($this->is_holliday[0] ?? '') . '</td>';
                }
            } else {
                if (get_option($week_day.'_heltlukket')) {
                    $returning .= '<td colspan="2" class="lukket">Lukket</td>';
                } else {
                    $openhour = get_option($week_day.'_aaben', '');
                    $closehour = get_option($week_day.'_lukket', '');
                    $returning .= '<td class="openhours">' . esc_html($openhour) . '</td>';
                    $returning .= '<td class="closinghours">' . esc_html($closehour) . '</td>';
                }          
            }
            $returning .= '</tr>';
        }
        
        $returning .= '</table></div>';
        return $returning;
    }

    public function show_collected_shopping_hour_week() {
        $hours_api = new CenterShop_Shopping_Hours();
        $week_dates = $hours_api->current_week_dates();
        // Group days with identical opening hours. An extra date stays on its own weekday.
        $days_groups = [];
        $processed = [];
        
        for ($i = 0; $i < 7; $i++) {
            if (isset($processed[$i])) continue;
            
            $resolved = $hours_api->resolve_hours_for_date($week_dates[$i]);
            $group = [
                'days' => [$this->week_days_display[$i]],
                'aaben' => $resolved['open'],
                'lukket' => $resolved['close'],
                'heltlukket' => $resolved['closed'],
                'note' => $resolved['override'] ? $hours_api->format_ekstra_dato_note($resolved) : '',
            ];
            
            // Find consecutive days with same hours. A special date breaks the run.
            if (!$resolved['override']) {
                for ($j = $i + 1; $j < 7; $j++) {
                    if (isset($processed[$j])) continue;
                    
                    $compare = $hours_api->resolve_hours_for_date($week_dates[$j]);
                    if ($compare['override']) {
                        break;
                    }
                    
                    if ($resolved['open'] === $compare['open'] && $resolved['close'] === $compare['close'] && $resolved['closed'] === $compare['closed']) {
                        $group['days'][] = $this->week_days_display[$j];
                        $processed[$j] = true;
                    } else {
                        break; // Stop at first non-matching day
                    }
                }
            }
            
            $days_groups[] = $group;
            $processed[$i] = true;
        }
        
        $returning = '<div class="centeraabningstider opsamlet">';
        foreach ($days_groups as $group) {
            $returning .= '<div class="days-together-shooping-hours">';
            
            // If more than 2 days, show "Mandag til Onsdag" instead of "Mandag - Tirsdag - Onsdag"
            if (count($group['days']) > 2) {
                $returning .= esc_html($group['days'][0]) . ' til ' . esc_html(end($group['days']));
            } else {
                $returning .= esc_html(implode(' - ', $group['days']));
            }
            if (!empty($group['note'])) {
                $returning .= '<span class="ekstra-dato-note">' . esc_html($group['note']) . '</span>';
            }
            
            $returning .= '</div>';
            
            if ($group['heltlukket']) {
                $returning .= '<div class="hours-for-days-together-shopping-hours">Lukket</div>';
            } elseif (!empty($group['aaben']) && !empty($group['lukket'])) {
                $returning .= '<div class="hours-for-days-together-shopping-hours">' . esc_html($group['aaben']) . ' - ' . esc_html($group['lukket']) . '</div>';
            } else {
                $returning .= '<div class="hours-for-days-together-shopping-hours">Lukket</div>';
            }
        }
        $returning .= '</div>';

        return $returning;
    }

    public function show_shopping_hours_today() {
        $hours_api = new CenterShop_Shopping_Hours();
        $resolved = $hours_api->resolve_hours_for_date(current_time('Y-m-d'));
        $heltlukket = $resolved['closed'];
        $aaben = $resolved['open'];
        $lukket = $resolved['close'];
        
        $returning = '<div class="todays-shopping-hours">';
        $returning .= '<p class="shopping-hour-title">Åbningstid i dag</p>';
        if ($resolved['override']) {
            $note = $hours_api->format_ekstra_dato_note($resolved);
            if ($note !== '') {
                $returning .= '<span class="ekstra-dato-note">' . esc_html($note) . '</span>';
            }
        }
        
        // Check if completely closed
        if ($heltlukket) {
            $returning .= '<span class="shopping-hour-closed-today">Lukket</span>';
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

    /**
     * Two hour cells, or a single closed cell.
     */
    private function render_hour_cells(bool $closed, string $open, string $close): string {
        if ($closed || $open === '' || $close === '') {
            return '<td colspan="2" class="lukket">Lukket</td>';
        }
        return '<td class="openhours">' . esc_html($open) . '</td><td class="closinghours">' . esc_html($close) . '</td>';
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
