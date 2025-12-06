<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ===================================================================
// --- SHORTCODE: [single_play_calendar] ---
// ===================================================================

if ( ! function_exists( 'my_single_play_calendar_shortcode' ) ) {

    function my_single_play_calendar_shortcode( $atts ) {
        
        $atts = shortcode_atts( [ 'id' => get_the_ID() ], $atts, 'single_play_calendar' );
        $play_id = (int) $atts['id'];

        if ( ! $play_id || get_post_type($play_id) !== 'play' ) {
            return '<!-- [single_play_calendar] - Play ID not found or invalid. -->';
        }

        $pod = pods( 'play', $play_id );
        if ( ! $pod || ! $pod->exists() ) {
            return '<!-- [single_play_calendar] - Play pod not found. -->';
        }

        $options = get_option( 'my_calendar_settings', [] );
        $global_colors = $options['global'] ?? [];
        $spc_year_text_color = $global_colors['spc_year_text_color'] ?? '#333333';
        $spc_dow_bg_color = $global_colors['spc_dow_bg_color'] ?? '#a7b59a';
        $spc_dow_text_color = $global_colors['spc_dow_text_color'] ?? '#222222';
        $spc_info_bg_color = $global_colors['spc_info_bg_color'] ?? '#c0cfb2';
        $spc_info_text_color = $global_colors['spc_info_text_color'] ?? '#222222';
        $spc_date_bg_color = $global_colors['spc_date_bg_color'] ?? '#d1dacb';
        $spc_date_text_color = $global_colors['spc_date_text_color'] ?? '#333333';

        $start_date_str = $pod->field( 'start_date' );
        if ( empty( $start_date_str ) ) return '';
        $calendar_year = date('Y', strtotime($start_date_str));
        
        $end_date_str = $pod->field( 'end_date' );
        $regular_show_time_str = $pod->field( 'regular_show_time' );
        $cancelled_dates_raw = $pod->field( 'cancellations' );
        $matinee_dates_raw = $pod->field( 'matinee_date' );
        $additional_dates_raw = $pod->field( 'added_dates' );
        
        $audition_info_html = '';
        $audition_events = $pod->field( 'audition_location' );
        if ( ! empty( $audition_events ) && is_array( $audition_events ) ) {
            $all_raw_dates = [];
            foreach ( $audition_events as $event_data ) {
                if ( empty( $event_data['ID'] ) ) continue;
                $event_pod = pods('event', $event_data['ID']);
                if ( ! $event_pod->exists() ) continue;
                $start_dates_array = $event_pod->field( 'event_start' );
                if ( !empty($start_dates_array) && is_array($start_dates_array) ) { foreach($start_dates_array as $date_str) { $all_raw_dates[] = $date_str; } }
            }
            if ( ! empty( $all_raw_dates ) ) {
                usort($all_raw_dates, function($a, $b) { return strtotime($a) <=> strtotime($b); });
                $formatted_dates = array_map(function($d) { return date('M j', strtotime($d)); }, $all_raw_dates);
                $audition_info_html = '<strong>Audition Dates:</strong><br>' . implode(', ', $formatted_dates);
            }
        }
        
        $all_events = [];
        $cancellations_list = [];
        if ( is_array($cancelled_dates_raw) ) {
            foreach ( $cancelled_dates_raw as $cancelled_date_str ) {
                if ( !empty($cancelled_date_str) ) { $cancellations_list[] = date( 'Y-m-d', strtotime($cancelled_date_str) ); }
            }
        }

        try {
            if (!empty($end_date_str)) {
                $period = new DatePeriod( new DateTime($start_date_str), new DateInterval('P1D'), (new DateTime($end_date_str))->modify('+1 day') );
                foreach ( $period as $date ) {
                    if ( in_array( $date->format( 'N' ), [ 4, 5, 6 ] ) ) { $all_events[] = [ 'date' => $date, 'type' => 'performance' ]; }
                }
            }
            
            if (is_array($matinee_dates_raw)) {
                foreach ( $matinee_dates_raw as $matinee_str ) {
                    $matinee_str = trim((string) $matinee_str);
                    if ($matinee_str === '' || $matinee_str === '0000-00-00 00:00:00' || strtotime($matinee_str) <= 0) continue;
                    $all_events[] = [ 'date' => new DateTime( $matinee_str ), 'type' => 'matinee' ];
                }
            }

            if ( ! empty( $additional_dates_raw ) && is_array( $additional_dates_raw ) ) {
                foreach ( $additional_dates_raw as $added_date_str ) {
                    $added_date_str = trim( (string) $added_date_str );
                    if ( $added_date_str === '' || $added_date_str === '0000-00-00 00:00:00' || strtotime( $added_date_str ) <= 0 ) continue;
                    try {
                        $added_date_obj = new DateTime( $added_date_str );
                        $all_events[] = [
                            'date' => $added_date_obj,
                            'type' => 'offday_added',
                            'text' => 'Added Date:<br><span style="color: red;">' . esc_html($added_date_obj->format('M j')) . '</span>'
                        ];
                    } catch ( Exception $e ) { continue; }
                }
            }
        } catch ( Exception $e ) { return '<!-- Calendar Error: Invalid date format. -->'; }

        usort( $all_events, function( $a, $b ) { return $a['date'] <=> $b['date']; });
        
        ob_start();
        ?>
        <div class="single-play-calendar">
            <h2 class="spc-item spc-year" style="color: <?php echo esc_attr( $spc_year_text_color ); ?>;"><?php echo esc_html( $calendar_year ); ?></h2>
            
            <div class="spc-item spc-button" style="background-color: <?php echo esc_attr( $spc_dow_bg_color ); ?>; color: <?php echo esc_attr( $spc_dow_text_color ); ?>;">THUR</div>
            <div class="spc-item spc-button" style="background-color: <?php echo esc_attr( $spc_dow_bg_color ); ?>; color: <?php echo esc_attr( $spc_dow_text_color ); ?>;">FRI</div>
            <div class="spc-item spc-button" style="background-color: <?php echo esc_attr( $spc_dow_bg_color ); ?>; color: <?php echo esc_attr( $spc_dow_text_color ); ?>;">SAT</div>

            <?php if ( ! empty( $audition_info_html ) ): ?>
                <div class="spc-item spc-info-box" style="background-color: <?php echo esc_attr( $spc_info_bg_color ); ?>; color: <?php echo esc_attr( $spc_info_text_color ); ?>;"><?php echo $audition_info_html; ?></div>
            <?php endif; ?>
            
            <?php 
            $unique_dates = []; 
            foreach ($all_events as $event) {
                $date_key = $event['date']->format('Y-m-d');
                if (!isset($unique_dates[$date_key])) { $unique_dates[$date_key] = [ 'types' => [], 'matinee_obj' => null ]; }
                
                $unique_dates[$date_key]['types'][] = $event['type'];
                
                // Capture the specific Matinee datetime object
                if ($event['type'] === 'matinee') {
                    $unique_dates[$date_key]['matinee_obj'] = $event['date'];
                }

                if (isset($event['text'])) { $unique_dates[$date_key]['text'] = $event['text']; }
            }
            uksort($unique_dates, function($a, $b) { return strtotime($a) <=> strtotime($b); });

            // --- DISPLAY LOOP ---
            foreach ($unique_dates as $date_key => $data) {
                $types = $data['types'];
                $date_obj = new DateTime($date_key);
                $is_cancelled = in_array( $date_obj->format('Y-m-d'), $cancellations_list );

                // 1. FIRST: Check for Regular Performance and print the BUTTON.
                // This ensures the button renders in the grid flow first.
                if (in_array('performance', $types)) {
                    $css_classes = ['spc-item', 'spc-button'];
                    $style = 'background-color:'.esc_attr($spc_date_bg_color).'; color:'.esc_attr($spc_date_text_color).';';
                    $display_text = $date_obj->format('M j');

                    if ($is_cancelled) {
                        $css_classes[] = 'cancelled';
                        $style = ''; 
                    }
                    
                    echo '<div class="' . esc_attr( implode(' ', $css_classes) ) . '" style="' . $style . '">' . $display_text . '</div>';
                }

                // 2. SECOND: Check for Matinee and print the INFO BOX.
                // This typically renders full-width, appearing "below" the grid row.
                if (in_array('matinee', $types)) {
                    // Use the captured matinee object to get the correct time
                    $mat_obj = $data['matinee_obj'] ? $data['matinee_obj'] : $date_obj;
                    
                    $css_classes = ['spc-item', 'spc-info-box'];
                    $style = 'background-color:'.esc_attr($spc_info_bg_color).'; color:'.esc_attr($spc_info_text_color).';';
                    $display_text = $mat_obj->format('l \M\a\t\i\n\e\e') . '<br>' . $mat_obj->format('M j @ g:ia');
                    
                    echo '<div class="' . esc_attr( implode(' ', $css_classes) ) . '" style="' . $style . '">' . $display_text . '</div>';
                }

                // 3. THIRD: Check for Added Off-Days (Also Info Boxes)
                if (in_array('offday_added', $types)) {
                     $css_classes = ['spc-item', 'spc-info-box'];
                     $style = 'background-color:'.esc_attr($spc_info_bg_color).'; color:'.esc_attr($spc_info_text_color).';';
                     $display_text = $data['text'];
                     echo '<div class="' . esc_attr( implode(' ', $css_classes) ) . '" style="' . $style . '">' . $display_text . '</div>';
                }
            }
            ?>
            
            <?php if ( ! empty( $regular_show_time_str ) ): ?>
                 <div class="spc-item spc-info-box" style="background-color: <?php echo esc_attr( $spc_info_bg_color ); ?>; color: <?php echo esc_attr( $spc_info_text_color ); ?>;">
                    Regular Show Time <?php echo esc_html( date('g:i A', strtotime($regular_show_time_str)) ); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    add_shortcode( 'single_play_calendar', 'my_single_play_calendar_shortcode' );
}
