<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ===================================================================
// --- Snippet 7: Calendar View Renderer: Dates View ---
// ===================================================================

if ( ! function_exists( 'my_project_render_calendar_dates_view' ) ) {
    /**
     * Renders just the formatted date list for a specific Play.
     * Requires the 'id' attribute to be set in the shortcode.
     *
     * @param array $atts The full shortcode attributes array.
     * @return string The HTML for the formatted date string, or empty string on failure.
     */
    function my_project_render_calendar_dates_view( $atts ) {
        // This view is entirely dependent on having a specific ID.
        if ( empty( $atts['id'] ) ) {
            return '<!-- Error: The "dates" view requires an "id" attribute. -->';
        }
        
        $play_id = intval( $atts['id'] );

        // Check if the post type is actually a 'play'.
        if ( get_post_type($play_id) !== 'play' ) {
            return '<!-- Error: The provided ID is not a Play. -->';
        }

        // Fetch the Pods object for the specified play.
        $play_pod = pods( 'play', $play_id );

        // If the pod doesn't exist, we cannot proceed.
        if ( ! $play_pod || ! $play_pod->exists() ) {
            return '';
        }

        // Call the centralized helper function (from Snippet 2) to do all the work.
        if ( function_exists( 'my_project_get_play_date_string' ) ) {
            return my_project_get_play_date_string( $play_pod );
        }

        return '<!-- Error: Date string helper function is missing. -->';
    }
}
