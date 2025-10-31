<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ===================================================================
// --- Snippet 11: Calendar Cache Invalidation ---
// ===================================================================

if ( ! function_exists( 'my_project_invalidate_calendar_cache' ) ) {
    /**
     * Updates the cache version number to invalidate all calendar transients.
     */
    function my_project_invalidate_calendar_cache() {
        // We use time() to ensure a unique version number every time.
        update_option('my_calendar_cache_version', time());
    }

    /**
     * This function is hooked to post saving/deleting. It checks if the
     * post is a 'play' or 'event' before invalidating the cache.
     *
     * @param int $post_id The ID of the post being saved or deleted.
     */
    function my_project_check_and_invalidate_cache( $post_id ) {
        // If this is a revision, do nothing.
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        $post_type = get_post_type( $post_id );

        // Only invalidate if the post type is one of ours.
        if ( in_array( $post_type, [ 'play', 'event' ] ) ) {
            my_project_invalidate_calendar_cache();
        }
    }

    // Hook our invalidation function to the relevant actions.
    add_action( 'save_post', 'my_project_check_and_invalidate_cache' );
    add_action( 'delete_post', 'my_project_check_and_invalidate_cache' );
}
