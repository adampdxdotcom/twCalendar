<?php
/**
 * Plugin Name:       TW Calendar
 * Plugin URI:        https://example.com/plugins/the-basics/
 * Description:       A custom calendar plugin built for the Theatre West website
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Adam Michaels
 * Author URI:        https://author.example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tw-calendar
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/shortcode_router.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/cal_functions.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/play_date_list.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/view_grid_list.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/view_scroll.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/view_scroll_featured.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/view_dates.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/audition_dates.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/cache_invalidation.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/single_play_calendar.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/ical_handler.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/event-editor.php';


/**
 * Enqueue assets for the admin area.
 * This function will load our front-end stylesheet on our custom dashboard page.
 */
function tw_calendar_enqueue_admin_assets( $hook_suffix ) {
    // We only want to load the calendar styles on the main 'event' list page, where our dashboard is.
    if ( 'edit.php' === $hook_suffix && 'event' === get_current_screen()->post_type ) {
        wp_enqueue_style(
            'tw-calendar-styles', // Use the same handle as the front-end
            plugin_dir_url( __FILE__ ) . 'assets/css/styles.css', // The path to the stylesheet
            array(),
            '1.0.0'
        );

        // ALSO enqueue the scroller script, since the shortcode needs it.
        wp_enqueue_script(
            'tw-calendar-scroller', // Use the same handle as the front-end
            plugin_dir_url( __FILE__ ) . 'assets/js/scroller.js',
            array(), // This script has no dependencies like jQuery
            '1.0.0',
            true     // Load in the footer
        );
    }
}
add_action( 'admin_enqueue_scripts', 'tw_calendar_enqueue_admin_assets' );


/** Enqueue scripts and styles for the front-end. */
function tw_calendar_enqueue_assets() {
	
    // Enqueue the main stylesheet
    wp_enqueue_style(
        'tw-calendar-styles', // A unique name (handle) for our stylesheet
        plugin_dir_url( __FILE__ ) . 'assets/css/styles.css', // The full URL to the stylesheet
        array(), // An array of stylesheet handles this style depends on
        '1.0.0'  // The version of your stylesheet
    );
	
    // Enqueue the scroller script
    wp_enqueue_script(
        'tw-calendar-scroller', // A unique name (handle) for our script
        plugin_dir_url( __FILE__ ) . 'assets/js/scroller.js', // The full URL to the script
        array(), // An array of script handles this script depends on (e.g., 'jquery')
        '1.0.0', // The version of your script
        true     // Load the script in the footer
    );
	
	    // Enqueue the modal window script
    wp_enqueue_script(
        'tw-calendar-modal-window', // A unique name (handle) for our script
        plugin_dir_url( __FILE__ ) . 'assets/js/modal_window.js', // The full URL to the script
        array(), // An array of script handles this script depends on (e.g., 'jquery')
        '1.0.0', // The version of your script
        true     // Load the script in the footer
    );
}
add_action( 'wp_enqueue_scripts', 'tw_calendar_enqueue_assets' );


// =========================================================================
// == Centralized Logging to TW Status Center (NEWLY ADDED)
// =========================================================================

/**
 * Logs when a Calendar Event post is created or updated.
 *
 * This function is attached to the 'save_post' hook.
 *
 * @param int     $post_id The ID of the post being saved.
 * @param WP_Post $post    The post object.
 * @param bool    $update  Whether this is an update to an existing post.
 */
function tw_calendar_log_post_changes( $post_id, $post, $update ) {
	// 1. Check if our logging function even exists. If not, stop.
	if ( ! function_exists( 'tw_suite_log' ) ) {
		return;
	}

	// 2. We only care about the 'event' post type.
	if ( 'event' !== $post->post_type ) {
		return;
	}

	// 3. Ignore auto-saves, revisions, and non-published posts to prevent log spam.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( wp_is_post_revision( $post_id ) ) { return; }
    if ( 'publish' !== $post->post_status ) { return; }

	// 4. Get the user who made the change.
	$user = get_user_by( 'id', $post->post_author );
	$user_name = $user ? $user->display_name : 'System';

	// 5. Determine if it was a creation or an update.
	$action = $update ? 'updated' : 'created';

	// 6. Construct the final log message.
	$message = sprintf(
		'Event "%s" was %s by %s.',
		esc_html( $post->post_title ),
		$action,
		$user_name
	);

	// 7. Send the log to the Status Center.
	tw_suite_log( 'TW Calendar', $message, 'INFO' );
}
add_action( 'save_post', 'tw_calendar_log_post_changes', 10, 3 );


/**
 * Logs when a Calendar Event post is deleted.
 *
 * This function is attached to the 'before_delete_post' hook.
 *
 * @param int $post_id The ID of the post being deleted.
 */
function tw_calendar_log_deleted_post( $post_id ) {
	// 1. Check if our logging function exists.
	if ( ! function_exists( 'tw_suite_log' ) ) {
		return;
	}

	// 2. Get the post object before it's gone.
	$post = get_post( $post_id );
	if ( ! $post || 'event' !== $post->post_type ) {
		return;
	}

	// 3. Get the user who is performing the deletion.
	$user = wp_get_current_user();
	$user_name = $user ? $user->display_name : 'System';

	// 4. Construct the final message.
	$message = sprintf( 'Event "%s" was deleted by %s.', esc_html( $post->post_title ), $user_name );

	// 5. Send the log. We use 'WARNING' as the level because deletion is a significant event.
	tw_suite_log( 'TW Calendar', $message, 'WARNING' );
}
add_action( 'before_delete_post', 'tw_calendar_log_deleted_post' );
