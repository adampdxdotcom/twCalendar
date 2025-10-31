<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ===================================================================
// --- Snippet 2: Calendar Helper Functions ---
// ===================================================================

// --- HELPER 1: GENERATE PLAY PERFORMANCES (Unchanged) ---
if ( ! function_exists( 'my_project_get_play_performances' ) ) {
    function my_project_get_play_performances( $play_pod, $start_range, $end_range, $limit = -1 ) {
        if ( ! $play_pod->exists() ) { return []; }
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
        $performances = [];
        $play_data = [ 'id' => $play_pod->id(), 'name' => (string) $play_pod->display('post_title'), 'permalink' => (string) $play_pod->field('permalink'), 'color' => $play_pod->display('calendar_color'), 'text_color' => $play_pod->display('calendar_color_text'), ];
        $play_start_str = $play_pod->field('start_date'); $play_end_str = $play_pod->field('end_date');
        if ( empty($play_start_str) || empty($play_end_str) ) { return []; }
        $play_start_date = new DateTime($play_start_str, $tz); $play_end_date = (new DateTime($play_end_str, $tz))->setTime(23, 59, 59);
        if ($play_end_date < $start_range || $play_start_date > $end_range) { return []; }
        $period_start = max($play_start_date, $start_range); $period_end = min($play_end_date, $end_range)->modify('+1 day');
        $regular_time_str = $play_pod->field('regular_show_time'); $regular_time_ts = !empty($regular_time_str) ? strtotime($regular_time_str) : false;
        $exceptions = [];
        foreach ($play_pod->field('added_dates') ?: [] as $date_str) { $exceptions[date('Y-m-d', strtotime($date_str))] = 'added'; }
        foreach ($play_pod->field('cancellations') ?: [] as $date_str) { $exceptions[date('Y-m-d', strtotime($date_str))] = 'cancelled'; }
        if ($period_start < $period_end) {
            $period = new DatePeriod($period_start, new DateInterval('P1D'), $period_end);
            foreach ($period as $date) {
                $date_str = $date->format('Y-m-d'); $status = 'regular';
                if (isset($exceptions[$date_str])) { $status = $exceptions[$date_str];
                } elseif (!in_array((int)$date->format('N'), [4, 5, 6])) { continue; }
                if ($status === 'cancelled') continue;
                $dt_str = $date_str . ($regular_time_ts ? ' ' . date('H:i:s', $regular_time_ts) : ' 00:00:00');
                $dt = new DateTime($dt_str, $tz);
                $performances[] = array_merge($play_data, [ 'sort_timestamp' => $dt->getTimestamp(), 'formatted_date' => $dt->format('F j'), 'formatted_time' => $regular_time_ts ? date('g:i A', $regular_time_ts) : 'All Day', 'is_matinee' => false, 'status' => $status ]);
            }
        }
        foreach ($play_pod->field('matinee_date') ?: [] as $datetime_str) {
            $matinee_obj = new DateTime($datetime_str, $tz);
            if ($matinee_obj >= $start_range && $matinee_obj <= $end_range) {
                $performances[] = array_merge($play_data, [ 'sort_timestamp' => $matinee_obj->getTimestamp(), 'formatted_date' => $matinee_obj->format('F j'), 'formatted_time' => $matinee_obj->format('g:i A'), 'is_matinee' => true, 'status' => 'regular' ]);
            }
        }
        usort($performances, function($a, $b) { return $a['sort_timestamp'] <=> $b['sort_timestamp']; });
        if ($limit > 0) { return array_slice($performances, 0, $limit); }
        return $performances;
    }
}

// --- HELPER 2: GENERATE FORMATTED PLAY DATE STRING (Unchanged) ---
if ( ! function_exists( 'my_project_get_play_date_string' ) ) {
    function my_project_get_play_date_string( $play_pod ) {
        if ( ! $play_pod || ! $play_pod->exists() ) { return ''; }
        $start_date_str = $play_pod->field( 'start_date' ); $end_date_str = $play_pod->field( 'end_date' ); $regular_show_time_str = $play_pod->field( 'regular_show_time' );
        if ( empty( $start_date_str ) || empty( $end_date_str ) ) { return ''; }
        $all_performance_dates = [];
        try {
            $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
            $start_date = new DateTime( $start_date_str, $tz );
            $end_date_inclusive = (new DateTime( $end_date_str, $tz ))->modify( '+1 day' );
            $period = new DatePeriod( $start_date, new DateInterval( 'P1D' ), $end_date_inclusive );
            foreach ( $period as $date ) { if ( in_array( $date->format( 'N' ), [ 4, 5, 6 ] ) ) { $all_performance_dates[] = $date; } }
        } catch ( Exception $e ) { return '<!-- Invalid date format -->'; }
        if ( empty( $all_performance_dates ) ) { return ''; }
        $output_parts = []; $range_buffer = []; $last_month = '';
        foreach ( $all_performance_dates as $index => $current_date ) {
            if ( empty( $range_buffer ) ) { $range_buffer[] = $current_date; } else {
                $last_date_in_buffer = end( $range_buffer ); $interval = $last_date_in_buffer->diff( $current_date );
                if ( $interval->days === 1 ) { $range_buffer[] = $current_date; } else {
                    $output_parts[] = format_date_range( $range_buffer, $last_month );
                    $last_month = end( $range_buffer )->format('M'); $range_buffer = [ $current_date ];
                }
            }
        }
        if ( ! empty( $range_buffer ) ) { $output_parts[] = format_date_range( $range_buffer, $last_month ); }
        $final_output = '<span class="play-date-ranges">' . esc_html( implode( ', ', $output_parts ) ) . '</span>';
        if ( ! empty( $regular_show_time_str ) ) {
            try {
                $time_obj = new DateTime( $regular_show_time_str ); $formatted_time = $time_obj->format('g:i A');
                $final_output .= '<br><span class="play-show-time">Shows start at ' . esc_html( $formatted_time ) . '</span>';
            } catch ( Exception $e ) { }
        }
        return $final_output;
    }
}

// --- HELPER 3: FORMATS A DATE RANGE (Unchanged) ---
if ( ! function_exists( 'format_date_range' ) ) {
    function format_date_range( $date_array, &$last_month ) {
        $start = $date_array[0]; $end = end( $date_array ); $count = count( $date_array );
        $current_month = $start->format( 'M' );
        $month_prefix = ( $current_month !== $last_month ) ? $current_month . '. ' : '';
        if ( $count >= 3 ) { return $month_prefix . $start->format( 'j' ) . '-' . $end->format( 'j' ); } 
        elseif ( $count === 2 ) { return $month_prefix . $start->format( 'j' ) . ', ' . $end->format( 'j' ); } 
        else { return $month_prefix . $start->format( 'j' ); }
    }
}

// =============================================================
// --- NEW HELPER 4: GENERATE FORMATTED EVENT DATE STRING ---
// =============================================================
if ( ! function_exists( 'my_project_get_event_date_string' ) ) {
    function my_project_get_event_date_string( $date_array, $time_str = '' ) {
        if ( empty($date_array) || !is_array($date_array) ) { return ''; }

        $date_objects = [];
        try {
            $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
            foreach ($date_array as $date_str) {
                if (empty(trim($date_str))) continue;
                $date_objects[] = new DateTime($date_str, $tz);
            }
        } catch (Exception $e) { return '<!-- Invalid date format -->'; }

        if (empty($date_objects)) return '';
        
        // Sort dates chronologically just in case
        usort($date_objects, function($a, $b) { return $a <=> $b; });

        $output_parts = []; $range_buffer = []; $last_month = '';
        foreach ( $date_objects as $current_date ) {
            if ( empty( $range_buffer ) ) { $range_buffer[] = $current_date; } else {
                $last_date_in_buffer = end( $range_buffer );
                // We only check for consecutive calendar days, ignoring time
                $interval = $last_date_in_buffer->diff($current_date);
                if ( $interval->days === 1 && $last_date_in_buffer->format('Y-m-d') !== $current_date->format('Y-m-d')) {
                    $range_buffer[] = $current_date;
                } else {
                    $output_parts[] = format_date_range( $range_buffer, $last_month );
                    $last_month = end( $range_buffer )->format('M'); $range_buffer = [ $current_date ];
                }
            }
        }
        if ( ! empty( $range_buffer ) ) { $output_parts[] = format_date_range( $range_buffer, $last_month ); }

        $final_output = '<span class="play-date-ranges">' . esc_html( implode( ', ', $output_parts ) ) . '</span>';
        if ( !empty($time_str) ) {
            $final_output .= '<br><span class="play-show-time">' . esc_html($time_str) . '</span>';
        }

        return $final_output;
    }
}
