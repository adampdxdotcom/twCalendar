/**
 * Universal iCal Download Handler.
 *
 * This version is now timezone-aware and correctly calculates the end time
 * for all event types, fixing the "12:30 PM" bug.
 */
function universal_calendar_ical_download_handler() {
    // 1. Check if this is an iCal download request.
    if ( ! isset( $_GET['download_ical'] ) ) {
        return;
    }

    // 2. Sanitize and validate all the required inputs from the URL.
    $post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
    $post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
    $datetime_str = isset( $_GET['datetime'] ) ? urldecode( $_GET['datetime'] ) : '';
    $is_matinee = isset( $_GET['is_matinee'] ) ? intval( $_GET['is_matinee'] ) : 0;

    if ( ! $post_id || ! in_array( $post_type, ['play', 'event'] ) || empty( $datetime_str ) ) {
        return;
    }

    // --- THE #1 FIX: Make this function aware of your site's timezone ---
    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');

    // 3. Load the correct Pod based on the post_type.
    $pod = pods( $post_type, $post_id );
    if ( ! $pod->exists() ) {
        return;
    }

    // 4. Initialize variables and determine event details based on the Pod type.
    $url = $pod->field( 'permalink' );
    
    // Create the start time object using the site's timezone. This is critical.
    $start_datetime = new DateTime( $datetime_str, $tz );
    
    $title = '';
    $location = '';
    $end_datetime = null; // Initialize end time as null

    if ( 'play' === $post_type ) {
        $title = $pod->display( 'post_title' );
        if ( $is_matinee ) {
            $title = 'Matinee: ' . $title;
        }
        // For plays, we still assume a standard duration.
        $end_datetime = (clone $start_datetime)->modify( '+150 minutes' );

    } else { // It's an 'event'
        $title = $pod->display( 'event_name' );
        $location = $pod->display( 'address' );

        // --- THE #2 FIX: Correctly check for a specific event_end time ---
        $end_date_str = $pod->field('event_end');
        // Check if an end date exists and is actually after the start time.
        if ( !empty($end_date_str) && strtotime($end_date_str) > $start_datetime->getTimestamp() ) {
            $end_datetime = new DateTime($end_date_str, $tz);
        } else {
            // Fallback to a default 2-hour duration if no valid end time is set.
            $end_datetime = (clone $start_datetime)->modify( '+120 minutes' );
        }
    }

    // 5. Format dates for the iCal file (UTC timezone).
    $start_utc = gmdate( 'Ymd\THis\Z', $start_datetime->getTimestamp() );
    $end_utc = gmdate( 'Ymd\THis\Z', $end_datetime->getTimestamp() );
    $timestamp = gmdate( 'Ymd\THis\Z' );
    
    // 6. Set headers to trigger a file download.
    header( 'Content-Type: text/calendar; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . sanitize_title( $title ) . '.ics"' );
    
    // 7. Echo the iCal content.
    echo "BEGIN:VCALENDAR\r\n";
    echo "VERSION:2.0\r\n";
    echo "PRODID:-//Your Website//NONSGML v1.0//EN\r\n";
    echo "BEGIN:VEVENT\r\n";
    echo "UID:event-{$post_id}-{$start_datetime->getTimestamp()}@" . parse_url( home_url(), PHP_URL_HOST ) . "\r\n";
    echo "DTSTAMP:{$timestamp}\r\n";
    echo "DTSTART:{$start_utc}\r\n";
    echo "DTEND:{$end_utc}\r\n";
    echo "SUMMARY:" . $title . "\r\n";
    if ( ! empty($location) ) echo "LOCATION:" . str_replace(["\r\n", "\n"], "\\n", $location) . "\r\n";
    if ( ! empty($url) ) echo "URL:" . $url . "\r\n";
    echo "END:VEVENT\r\n";
    echo "END:VCALENDAR\r\n";
    
    exit();
}
add_action( 'init', 'universal_calendar_ical_download_handler', 1 ); // Added priority to ensure it runs
