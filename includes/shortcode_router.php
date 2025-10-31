<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ===================================================================
// --- Snippet 1: Main Calendar Shortcode Router ---
// ===================================================================

if ( ! function_exists( 'monthly_events_calendar_shortcode' ) ) {
    function monthly_events_calendar_shortcode( $atts ) {
        // Ensure $atts is an array
        $atts = is_array($atts) ? $atts : [];

        // Set defaults for ALL attributes used by ANY view
        $defaults = [
            'view'          => '',
            'class'         => '',
            'limit'         => 10,
            'more_page'     => 'events',
            'id'            => null,
            'visible_items' => 5,
        ];
        $atts = shortcode_atts($defaults, $atts, 'monthly_events_calendar');

        // Determine the current view.
        if ( ! empty( $atts['view'] ) ) {
            $current_view = sanitize_text_field( $atts['view'] );
        } elseif ( $atts['class'] === 'sidebar-widget' ) {
            $current_view = 'list';
        } elseif ( isset( $_GET['view'] ) ) {
            $current_view = sanitize_text_field( $_GET['view'] );
        } else {
            $current_view = 'grid';
        }

        // Route to the appropriate rendering function.
        switch ( $current_view ) {
            case 'scroll':
                if ( function_exists( 'my_project_render_calendar_scroll_view' ) ) {
                    return my_project_render_calendar_scroll_view( $atts );
                }
                return '<!-- Error: Scroll view function is missing. -->';
            case 'scroll-featured':
                if ( function_exists( 'my_project_render_calendar_scroll_featured_view' ) ) {
                    return my_project_render_calendar_scroll_featured_view( $atts );
                }
                return '<!-- Error: Scroll-Featured view function is missing. -->';
            case 'dates':
                if ( function_exists( 'my_project_render_calendar_dates_view' ) ) {
                    return my_project_render_calendar_dates_view( $atts );
                }
                return '<!-- Error: Dates view function is missing. -->';
            case 'grid':
            case 'list':
                if ( function_exists( 'my_project_render_calendar_monthly_view' ) ) {
                    return my_project_render_calendar_monthly_view( $atts, $current_view );
                }
                return '<!-- Error: Monthly view function is missing. -->';
            default:
                return '<!-- Invalid calendar view specified. -->';
        }
    }
    add_shortcode( 'monthly_events_calendar', 'monthly_events_calendar_shortcode' );
}
