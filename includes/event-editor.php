<?php
/**
 * Customizations for the 'Event' Pod Editor Screen.
 *
 * This file disables the Block Editor for the 'event' post type to provide a
 * stable, simple data-entry screen, mirroring the functionality of the
 * TW Plays and TW Forms plugins for a consistent user experience.
 *
 * @package TW_Calendar
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Disables the Block Editor for the 'event' post type.
 *
 * We use a high priority (99) to ensure this function runs after the
 * post type has been registered by Pods or any other source.
 */
function tw_calendar_disable_block_editor_for_event() {
    remove_post_type_support( 'event', 'editor' );
}
add_action( 'init', 'tw_calendar_disable_block_editor_for_event', 99 );
