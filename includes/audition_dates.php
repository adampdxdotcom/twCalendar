/**
 * Creates the [audition_dates] shortcode.
 * Usage: [audition_dates id="123"] (where 123 is a Play ID)
 *     or [audition_dates] (when placed on a Play page)
 */
function tw_calendar_audition_dates_shortcode( $atts ) {
    // 1. Get the Play ID, either from the attribute or the current page.
    $atts = shortcode_atts( array( 'id' => '' ), $atts );
    if ( ! empty( $atts['id'] ) ) {
        $play_id = intval( $atts['id'] );
    } else {
        $play_id = get_the_ID();
    }

    if ( ! $play_id ) {
        return '';
    }

    // 2. Load the Play Pod.
    $play_pod = pods( 'play', $play_id );
    if ( ! $play_pod->exists() ) {
        return '<!-- Play not found -->';
    }

    // 3. Get the list of related events from the 'audition_location' relationship field.
    $events = $play_pod->field( 'audition_location' );
    if ( empty( $events ) ) {
        return '';
    }

    // 4. Collect all valid dates, locations, and URLs into a master list.
    $all_auditions = [];
    foreach ( $events as $event_data ) {
        if ( empty( $event_data['ID'] ) ) continue;
        
        $event_pod = pods('event', $event_data['ID']);
        if ( ! $event_pod->exists() ) continue;

        $location = $event_pod->field( 'location_name' );
        $start_dates_array = $event_pod->field( 'event_start' );
        // --- NEW: Get the permalink for the event ---
        $event_url = $event_pod->field( 'permalink' );

        if ( !empty($location) && !empty($start_dates_array) && is_array($start_dates_array) ) {
            foreach ( $start_dates_array as $single_date_str ) {
                $all_auditions[] = [
                    'date_str' => $single_date_str,
                    'location' => $location,
                    // --- NEW: Store the URL with the other data ---
                    'url'      => $event_url 
                ];
            }
        }
    }

    // 5. If no valid auditions were found, stop.
    if ( empty($all_auditions) ) {
        return '';
    }

    // 6. Sort the master list chronologically by date.
    usort($all_auditions, function($a, $b) {
        return strtotime($a['date_str']) <=> strtotime($b['date_str']);
    });

    // 7. Build the final HTML output from the sorted list.
    $production_html = '<ul>';
    foreach ($all_auditions as $audition) {
        $start_timestamp = strtotime( $audition['date_str'] );
        $formatted_date = date( 'F j', $start_timestamp );
        $formatted_time = date( 'g:ia', $start_timestamp );
        
        // --- MODIFIED: Build the location name as a clickable link ---
        $location_text = esc_html( $audition['location'] );
        $location_url  = esc_url( $audition['url'] );
        $location_html = '<a href="' . $location_url . '">' . $location_text . '</a>';
        
        $sentence = $location_html . ' on ' . esc_html( $formatted_date ) . ' at ' . esc_html( $formatted_time ) . '.';
        // NOTE: The sentence now contains HTML, so we don't escape the whole thing.
        $production_html .= '<li>' . $sentence . '</li>';
    }
    $production_html .= '</ul>';

    return $production_html;
}
add_shortcode( 'audition_dates', 'tw_calendar_audition_dates_shortcode' );
