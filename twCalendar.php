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

require_once plugin_dir_path( __FILE__ ) . 'includes/shortcode-router.php';
