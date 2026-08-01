<?php
/**
 * Opening Hours v2 – structured JSON storage with Schema.org output
 *
 * Meta keys:
 *   butik_aabentider_v2      – JSON (see schema below)
 *   butik_aabentider_migrated – '1' when auto-migration has run
 *
 * JSON schema:
 * {
 *   "days": {
 *     "mandag":  { "inherit": bool, "status": "open|closed", "from": "HH:MM", "to": "HH:MM" },
 *     ...
 *   },
 *   "exceptions": [
 *     { "from_date": "YYYY-MM-DD", "to_date": "YYYY-MM-DD", "status": "open|closed", "label": "...", "from": "HH:MM", "to": "HH:MM" }
 *   ]
 * }
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

define( 'JXW_OH_DAYS', [
    'mandag'  => 'Mandag',
    'tirsdag' => 'Tirsdag',
    'onsdag'  => 'Onsdag',
    'torsdag' => 'Torsdag',
    'fredag'  => 'Fredag',
    'lordag'  => 'Lørdag',
    'sondag'  => 'Søndag',
] );

define( 'JXW_OH_SCHEMA_DAY_MAP', [
    'mandag'  => 'Monday',
    'tirsdag' => 'Tuesday',
    'onsdag'  => 'Wednesday',
    'torsdag' => 'Thursday',
    'fredag'  => 'Friday',
    'lordag'  => 'Saturday',
    'sondag'  => 'Sunday',
] );

// ---------------------------------------------------------------------------
// Data access
// ---------------------------------------------------------------------------

function jxw_get_butik_opening_hours( int $post_id ): ?array {
    $raw = get_post_meta( $post_id, 'butik_aabentider_v2', true );
    if ( empty( $raw ) ) {
        return null;
    }
    $data = json_decode( $raw, true );
    if ( ! is_array( $data ) ) {
        return null;
    }
    return $data;
}

/**
 * Resolve effective hours for a single day, honouring the "inherit" flag.
 * Returns [ 'status' => 'open|closed', 'from' => 'HH:MM', 'to' => 'HH:MM' ]
 */
function jxw_resolve_day_hours( int $post_id, string $day ): array {
    $data = jxw_get_butik_opening_hours( $post_id );
    $day_data = $data['days'][ $day ] ?? null;

    if ( $day_data && empty( $day_data['inherit'] ) ) {
        return [
            'status' => $day_data['status'] ?? 'open',
            'from'   => $day_data['from'] ?? '',
            'to'     => $day_data['to'] ?? '',
        ];
    }

    // Inherit from center
    $heltlukket = get_option( $day . '_heltlukket', false );
    if ( $heltlukket ) {
        return [ 'status' => 'closed', 'from' => '', 'to' => '' ];
    }

    return [
        'status' => 'open',
        'from'   => get_option( $day . '_aaben', '' ),
        'to'     => get_option( $day . '_lukket', '' ),
    ];
}

/**
 * Returns the first active exception for $date (default: today), or null.
 */
function jxw_get_active_exception( int $post_id, string $date = '' ): ?array {
    if ( $date === '' ) {
        $date = current_time( 'Y-m-d' );
    }
    $data = jxw_get_butik_opening_hours( $post_id );
    if ( empty( $data['exceptions'] ) ) {
        return null;
    }
    foreach ( $data['exceptions'] as $exc ) {
        if (
            isset( $exc['from_date'], $exc['to_date'] ) &&
            $date >= $exc['from_date'] &&
            $date <= $exc['to_date']
        ) {
            return $exc;
        }
    }
    return null;
}

// ---------------------------------------------------------------------------
// HTML rendering
// ---------------------------------------------------------------------------

function jxw_render_butik_hours_html( int $post_id ): string {
    $data = jxw_get_butik_opening_hours( $post_id );
    if ( ! $data ) {
        return '';
    }

    $today_key  = jxw_oh_current_day_key();
    $exception  = jxw_get_active_exception( $post_id );

    $html = '';

    if ( $exception ) {
        $label  = esc_html( $exception['label'] ?? 'Særlige åbningstider' );
        $status = $exception['status'] ?? 'closed';
        if ( $status === 'closed' ) {
            $from_fmt = $exception['from_date'] ? date_i18n( 'j. M Y', strtotime( $exception['from_date'] ) ) : '';
            $to_fmt   = $exception['to_date']   ? date_i18n( 'j. M Y', strtotime( $exception['to_date'] ) )   : '';
            $detail   = $from_fmt && $to_fmt ? '<span class="exception-dates">Fra: ' . $from_fmt . ' til: ' . $to_fmt . '</span>' : '';
            $html .= '<p class="butik-exception-row"><strong>' . $label . '</strong>' . $detail . '</p>';
        } else {
            $time_str = esc_html( $exception['from'] ?? '' ) . ' – ' . esc_html( $exception['to'] ?? '' );
            $html .= '<p class="butik-exception-row"><strong>' . $label . ':</strong> ' . $time_str . '</p>';
        }
    }

    $html .= '<table class="butik-aabentider-tabel">';
    foreach ( JXW_OH_DAYS as $key => $label ) {
        $hours      = jxw_resolve_day_hours( $post_id, $key );
        $is_today   = ( $key === $today_key );
        $row_class  = $is_today ? ' class="idag"' : '';

        if ( $hours['status'] === 'closed' ) {
            $time_str = '<span class="lukket">Lukket</span>';
        } else {
            $time_str = esc_html( $hours['from'] ) . ' – ' . esc_html( $hours['to'] );
        }

        $html .= '<tr' . $row_class . '>';
        $html .= '<td class="ugedag">' . esc_html( $label ) . '</td>';
        $html .= '<td class="openhours">' . $time_str . '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';

    return $html;
}

// ---------------------------------------------------------------------------
// Schema.org JSON-LD
// ---------------------------------------------------------------------------

function jxw_output_butik_schema_json_ld( int $post_id ): string {
    $data = jxw_get_butik_opening_hours( $post_id );
    if ( ! $data ) {
        return '';
    }

    $name   = get_post_meta( $post_id, 'butik_payed_name', true );
    if ( empty( $name ) ) {
        $name = get_the_title( $post_id );
    }

    $opening_hours = [];
    foreach ( array_keys( JXW_OH_DAYS ) as $day ) {
        $hours     = jxw_resolve_day_hours( $post_id, $day );
        $schema_day = JXW_OH_SCHEMA_DAY_MAP[ $day ];

        if ( $hours['status'] === 'closed' ) {
            $opening_hours[] = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => 'https://schema.org/' . $schema_day,
                'opens'     => '00:00',
                'closes'    => '00:00',
            ];
        } elseif ( $hours['from'] !== '' && $hours['to'] !== '' ) {
            $opening_hours[] = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => 'https://schema.org/' . $schema_day,
                'opens'     => $hours['from'],
                'closes'    => $hours['to'],
            ];
        }
    }

    $special_hours = [];
    foreach ( $data['exceptions'] ?? [] as $exc ) {
        if ( empty( $exc['from_date'] ) || empty( $exc['to_date'] ) ) {
            continue;
        }
        $entry = [
            '@type'        => 'OpeningHoursSpecification',
            'validFrom'    => $exc['from_date'],
            'validThrough' => $exc['to_date'],
        ];
        if ( ( $exc['status'] ?? 'closed' ) === 'closed' ) {
            $entry['opens']  = '00:00';
            $entry['closes'] = '00:00';
        } else {
            $entry['opens']  = $exc['from'] ?? '00:00';
            $entry['closes'] = $exc['to'] ?? '00:00';
        }
        if ( ! empty( $exc['label'] ) ) {
            $entry['name'] = $exc['label'];
        }
        $special_hours[] = $entry;
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@type'    => 'LocalBusiness',
        'name'     => $name,
    ];

    if ( ! empty( $opening_hours ) ) {
        $schema['openingHoursSpecification'] = $opening_hours;
    }
    if ( ! empty( $special_hours ) ) {
        $schema['specialOpeningHoursSpecification'] = $special_hours;
    }

    return '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>';
}

function jxw_maybe_output_butik_schema() {
    if ( ! is_singular( 'butiksside' ) ) {
        return;
    }
    $post_id = get_the_ID();
    if ( ! $post_id ) {
        return;
    }
    // Only output if v2 data exists
    if ( jxw_get_butik_opening_hours( $post_id ) ) {
        echo "\n" . jxw_output_butik_schema_json_ld( $post_id ) . "\n";
    }
}
add_action( 'wp_head', 'jxw_maybe_output_butik_schema' );

// ---------------------------------------------------------------------------
// Migration: HTML → JSON
// ---------------------------------------------------------------------------

/**
 * Parse legacy HTML table into days array.
 * Expects rows like: <tr><td>Mandag</td><td>10:00 - 18:00</td></tr>
 *                or: <tr><td>Søndag</td><td>Lukket</td></tr>
 */
function jxw_parse_html_opening_hours( string $html ): array {
    $day_name_map = [
        'mandag'   => 'mandag',
        'tirsdag'  => 'tirsdag',
        'onsdag'   => 'onsdag',
        'torsdag'  => 'torsdag',
        'fredag'   => 'fredag',
        'lørdag'   => 'lordag',
        'lordag'   => 'lordag',
        'søndag'   => 'sondag',
        'sondag'   => 'sondag',
    ];

    $days = [];
    foreach ( array_keys( JXW_OH_DAYS ) as $key ) {
        $days[ $key ] = [ 'inherit' => false, 'status' => 'open', 'from' => '', 'to' => '' ];
    }

    // Match <tr>...<td>DayName</td>...<td>Hours</td>...</tr>
    if ( ! preg_match_all( '/<tr[^>]*>.*?<td[^>]*>(.*?)<\/td>.*?<td[^>]*>(.*?)<\/td>.*?<\/tr>/is', $html, $matches ) ) {
        return $days;
    }

    foreach ( $matches[1] as $idx => $day_cell ) {
        $day_raw  = strtolower( trim( wp_strip_all_tags( $day_cell ) ) );
        $time_raw = trim( wp_strip_all_tags( $matches[2][ $idx ] ) );
        $day_key  = $day_name_map[ $day_raw ] ?? null;

        if ( ! $day_key ) {
            continue;
        }

        if ( stripos( $time_raw, 'lukket' ) !== false ) {
            $days[ $day_key ] = [ 'inherit' => false, 'status' => 'closed', 'from' => '', 'to' => '' ];
        } elseif ( preg_match( '/(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})/', $time_raw, $tm ) ) {
            $days[ $day_key ] = [
                'inherit' => false,
                'status'  => 'open',
                'from'    => $tm[1],
                'to'      => $tm[2],
            ];
        }
    }

    return $days;
}

/**
 * Auto-migration hook: runs on first save of a butiksside if not yet migrated.
 */
function jxw_maybe_migrate_opening_hours( int $post_id ) {
    if ( get_post_type( $post_id ) !== 'butiksside' ) {
        return;
    }
    if ( get_post_meta( $post_id, 'butik_aabentider_migrated', true ) ) {
        return;
    }
    $html = get_post_meta( $post_id, 'butik_aabentider', true );
    if ( empty( $html ) ) {
        return;
    }
    jxw_run_migration( $post_id, $html );
}
add_action( 'save_post_butiksside', 'jxw_maybe_migrate_opening_hours' );

function jxw_run_migration( int $post_id, string $html ) {
    $days = jxw_parse_html_opening_hours( $html );
    $data = [
        'days'       => $days,
        'exceptions' => [],
    ];
    update_post_meta( $post_id, 'butik_aabentider_v2', wp_json_encode( $data ) );
    update_post_meta( $post_id, 'butik_aabentider_migrated', '1' );
}

/**
 * AJAX handler for the "Konvertér nu" admin button.
 */
function jxw_ajax_migrate_opening_hours() {
    check_ajax_referer( 'jxw_oh_migrate', 'nonce' );

    $post_id = intval( $_POST['post_id'] ?? 0 );
    if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Adgang nægtet.' ] );
    }

    $html = get_post_meta( $post_id, 'butik_aabentider', true );
    if ( empty( $html ) ) {
        wp_send_json_error( [ 'message' => 'Ingen HTML-åbningstider fundet.' ] );
    }

    jxw_run_migration( $post_id, $html );
    wp_send_json_success( [ 'message' => 'Konvertering fuldført. Siden genindlæses...' ] );
}
add_action( 'wp_ajax_jxw_migrate_opening_hours', 'jxw_ajax_migrate_opening_hours' );

// ---------------------------------------------------------------------------
// Save handler (new metabox)
// ---------------------------------------------------------------------------

function jxw_save_butik_v2_opening_hours( int $post_id ) {
    if ( ! isset( $_POST['butik_v2_aabentider_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( $_POST['butik_v2_aabentider_nonce'], 'butik_v2_aabentider_action' ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    $raw_days = $_POST['butik_oh_days'] ?? [];
    $days     = [];

    foreach ( array_keys( JXW_OH_DAYS ) as $day ) {
        $inherit = ! empty( $raw_days[ $day ]['inherit'] );
        $status  = ( ( $raw_days[ $day ]['status'] ?? 'open' ) === 'closed' ) ? 'closed' : 'open';
        $from    = jxw_sanitize_time( $raw_days[ $day ]['from'] ?? '' );
        $to      = jxw_sanitize_time( $raw_days[ $day ]['to'] ?? '' );

        $days[ $day ] = [
            'inherit' => $inherit,
            'status'  => $status,
            'from'    => $from,
            'to'      => $to,
        ];
    }

    $raw_exceptions = $_POST['butik_oh_exceptions'] ?? [];
    $exceptions     = [];

    if ( is_array( $raw_exceptions ) ) {
        foreach ( $raw_exceptions as $exc ) {
            $from_date = jxw_sanitize_date( $exc['from_date'] ?? '' );
            $to_date   = jxw_sanitize_date( $exc['to_date'] ?? '' );
            if ( ! $from_date || ! $to_date ) {
                continue;
            }
            $status = ( ( $exc['status'] ?? 'closed' ) === 'open' ) ? 'open' : 'closed';
            $exceptions[] = [
                'from_date' => $from_date,
                'to_date'   => $to_date,
                'status'    => $status,
                'label'     => sanitize_text_field( $exc['label'] ?? '' ),
                'from'      => jxw_sanitize_time( $exc['from'] ?? '' ),
                'to'        => jxw_sanitize_time( $exc['to'] ?? '' ),
            ];
        }
    }

    $data = [
        'days'       => $days,
        'exceptions' => $exceptions,
    ];

    update_post_meta( $post_id, 'butik_aabentider_v2', wp_json_encode( $data ) );
    // Mark as migrated so auto-migration doesn't overwrite
    update_post_meta( $post_id, 'butik_aabentider_migrated', '1' );
}
add_action( 'save_post_butiksside', 'jxw_save_butik_v2_opening_hours' );

// ---------------------------------------------------------------------------
// Utility helpers
// ---------------------------------------------------------------------------

function jxw_sanitize_time( string $value ): string {
    $value = trim( $value );
    if ( preg_match( '/^\d{1,2}:\d{2}$/', $value ) ) {
        return $value;
    }
    return '';
}

function jxw_sanitize_date( string $value ): string {
    $value = trim( $value );
    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
        return $value;
    }
    return '';
}

function jxw_oh_current_day_key(): string {
    $map = [
        1 => 'mandag',
        2 => 'tirsdag',
        3 => 'onsdag',
        4 => 'torsdag',
        5 => 'fredag',
        6 => 'lordag',
        0 => 'sondag',
    ];
    return $map[ (int) current_time( 'w' ) ] ?? 'mandag';
}

// ---------------------------------------------------------------------------
// Admin metabox UI
// ---------------------------------------------------------------------------

function jxw_opening_hours_v2_metabox_html( WP_Post $post ) {
    $post_id   = $post->ID;
    $migrated  = get_post_meta( $post_id, 'butik_aabentider_migrated', true );
    $data      = jxw_get_butik_opening_hours( $post_id );
    $days_data = $data['days'] ?? [];
    $exceptions = $data['exceptions'] ?? [];

    wp_nonce_field( 'butik_v2_aabentider_action', 'butik_v2_aabentider_nonce' );
    ?>

    <?php if ( ! $migrated ) : ?>
    <div class="jxw-oh-migration-notice">
        <p>
            <strong>Ikke migreret endnu.</strong>
            Denne butik bruger stadig HTML-åbningstider. Klik for at konvertere automatisk.
        </p>
        <button type="button" class="button button-secondary" id="jxw-migrate-btn" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'jxw_oh_migrate' ) ); ?>">
            Konvertér nu
        </button>
        <span class="jxw-oh-migrate-result" style="margin-left:10px;"></span>
    </div>
    <?php endif; ?>

    <table class="jxw-oh-table">
        <thead>
            <tr>
                <th>Dag</th>
                <th>Arv fra center</th>
                <th>Status</th>
                <th>Åbner</th>
                <th>Lukker</th>
                <th class="jxw-oh-center-hint-col">Centerets tider</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( JXW_OH_DAYS as $day_key => $day_label ) :
            $day_d   = $days_data[ $day_key ] ?? [ 'inherit' => true, 'status' => 'open', 'from' => '', 'to' => '' ];
            $inherit = ! empty( $day_d['inherit'] );
            $closed  = ( $day_d['status'] ?? 'open' ) === 'closed';

            // Center hint
            $c_closed = get_option( $day_key . '_heltlukket', false );
            $c_from   = get_option( $day_key . '_aaben', '' );
            $c_to     = get_option( $day_key . '_lukket', '' );
            $c_hint   = $c_closed ? 'Lukket' : ( $c_from && $c_to ? $c_from . ' – ' . $c_to : '(ikke sat)' );
        ?>
            <tr class="jxw-oh-day-row" data-day="<?php echo esc_attr( $day_key ); ?>">
                <td class="jxw-oh-day-name"><strong><?php echo esc_html( $day_label ); ?></strong></td>
                <td class="jxw-oh-inherit-col">
                    <label class="screen-reader-text" for="oh_inherit_<?php echo esc_attr( $day_key ); ?>">Arv</label>
                    <input type="checkbox"
                           id="oh_inherit_<?php echo esc_attr( $day_key ); ?>"
                           name="butik_oh_days[<?php echo esc_attr( $day_key ); ?>][inherit]"
                           value="1"
                           <?php checked( $inherit ); ?>
                           class="jxw-oh-inherit-toggle" />
                </td>
                <td class="jxw-oh-status-col">
                    <select name="butik_oh_days[<?php echo esc_attr( $day_key ); ?>][status]"
                            class="jxw-oh-status-select"
                            <?php echo disabled( $inherit, true, false ); ?>>
                        <option value="open"   <?php selected( ! $closed ); ?>>Åben</option>
                        <option value="closed" <?php selected( $closed ); ?>>Lukket</option>
                    </select>
                </td>
                <td>
                    <input type="time"
                           name="butik_oh_days[<?php echo esc_attr( $day_key ); ?>][from]"
                           value="<?php echo esc_attr( $day_d['from'] ?? '' ); ?>"
                           class="jxw-oh-time-input"
                           <?php echo disabled( ( $inherit || $closed ), true, false ); ?> />
                </td>
                <td>
                    <input type="time"
                           name="butik_oh_days[<?php echo esc_attr( $day_key ); ?>][to]"
                           value="<?php echo esc_attr( $day_d['to'] ?? '' ); ?>"
                           class="jxw-oh-time-input"
                           <?php echo disabled( ( $inherit || $closed ), true, false ); ?> />
                </td>
                <td class="jxw-oh-center-hint"><?php echo esc_html( $c_hint ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h4 style="margin-top:20px;">Midlertidige ændringer / ferielukket</h4>
    <table class="jxw-oh-exceptions-table widefat" id="jxw-oh-exceptions">
        <thead>
            <tr>
                <th>Fra dato</th>
                <th>Til dato</th>
                <th>Status</th>
                <th>Åbner</th>
                <th>Lukker</th>
                <th>Beskrivelse</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="jxw-oh-exceptions-body">
        <?php foreach ( $exceptions as $idx => $exc ) : ?>
            <tr class="jxw-oh-exc-row">
                <td><input type="date" name="butik_oh_exceptions[<?php echo esc_attr( $idx ); ?>][from_date]" value="<?php echo esc_attr( $exc['from_date'] ?? '' ); ?>" class="jxw-oh-exc-input" /></td>
                <td><input type="date" name="butik_oh_exceptions[<?php echo esc_attr( $idx ); ?>][to_date]"   value="<?php echo esc_attr( $exc['to_date'] ?? '' ); ?>" class="jxw-oh-exc-input" /></td>
                <td>
                    <select name="butik_oh_exceptions[<?php echo esc_attr( $idx ); ?>][status]" class="jxw-oh-exc-status">
                        <option value="closed" <?php selected( ( $exc['status'] ?? 'closed' ) === 'closed' ); ?>>Lukket</option>
                        <option value="open"   <?php selected( ( $exc['status'] ?? '' ) === 'open' ); ?>>Åben</option>
                    </select>
                </td>
                <td><input type="time" name="butik_oh_exceptions[<?php echo esc_attr( $idx ); ?>][from]" value="<?php echo esc_attr( $exc['from'] ?? '' ); ?>" class="jxw-oh-exc-time" <?php echo disabled( ( ( $exc['status'] ?? 'closed' ) === 'closed' ), true, false ); ?> /></td>
                <td><input type="time" name="butik_oh_exceptions[<?php echo esc_attr( $idx ); ?>][to]"   value="<?php echo esc_attr( $exc['to'] ?? '' ); ?>" class="jxw-oh-exc-time" <?php echo disabled( ( ( $exc['status'] ?? 'closed' ) === 'closed' ), true, false ); ?> /></td>
                <td><input type="text" name="butik_oh_exceptions[<?php echo esc_attr( $idx ); ?>][label]" value="<?php echo esc_attr( $exc['label'] ?? '' ); ?>" placeholder="f.eks. Sommerlukket" class="jxw-oh-exc-input" /></td>
                <td><button type="button" class="button button-small jxw-oh-remove-exc">Fjern</button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p>
        <button type="button" class="button" id="jxw-oh-add-exc">+ Tilføj undtagelse</button>
    </p>
    <?php
}
