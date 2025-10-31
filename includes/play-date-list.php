<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ===================================================================
// --- Snippet 3: Legacy Shortcode: [play_date_list] ---
// ===================================================================

if ( ! function_exists( 'my_play_date_list_shortcode_function' ) ) {
    /**
     * Legacy shortcode [play_date_list] to display a formatted string of performance dates.
     * This is now a wrapper for the new my_project_get_play_date_string() helper function.
     *
     * @param array $atts Shortcode attributes, expects 'id'.
     * @return string The formatted HTML string of dates.
     */
    function my_play_date_list_shortcode_function( $atts ) {
        
        // Handle the 'id' attribute to find the correct post.
        $atts = shortcode_atts( [
            'id' => '',
        ], $atts );

        if ( ! empty( $atts['id'] ) ) {
            $current_post_id = intval( $atts['id'] );
        } else {
            $current_post_id = get_the_ID();
        }
        
        if ( ! $current_post_id ) {
            return ''; 
        }

        // Fetch the Pods object for the play.
        $pod = pods( 'play', $current_post_id );
        
        // If the pod doesn't exist, we can't proceed.
        if ( ! $pod || ! $pod->exists() ) {
            return '';
        }

        // Call the centralized helper function to generate the date string.
        // This avoids code duplication and keeps the logic in one place.
        if ( function_exists( 'my_project_get_play_date_string' ) ) {
            return my_project_get_play_date_string( $pod );
        }

        return '<!-- Error: Date string helper function is missing. -->';
    }
    
    // Register the shortcode.
    add_shortcode( 'play_date_list', 'my_play_date_list_shortcode_function' );
}
