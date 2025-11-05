<?php
/**
 * Plugin Name: FCHub Stream
 * Plugin URI: https://fchub.co
 * Description: Direct video upload plugin for FluentCommunity. Enables users to upload videos directly instead of only YouTube links. Supports Cloudflare Stream and Bunny.net Stream.
 * Version: 0.0.1
 * Author: Vibe Code
 * Author URI: https://x.com/vcode_sh
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fchub-stream
 * Domain Path: /languages
 * Requires at least: 6.7
 * Requires PHP: 8.3
 *
 * @package FCHub_Stream
 * @since 0.0.1
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @since 0.0.1
 */
define( 'FCHUB_STREAM_VERSION', '0.0.1' );

/**
 * Plugin mode (production/development).
 *
 * @since 0.0.1
 */
define( 'FCHUB_STREAM_MODE', 'development' );

/**
 * Plugin URL.
 *
 * @since 0.0.1
 */
define( 'FCHUB_STREAM_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin directory path.
 *
 * @since 0.0.1
 */
define( 'FCHUB_STREAM_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin main file path.
 *
 * @since 0.0.1
 */
define( 'FCHUB_STREAM_FILE', __FILE__ );

// Require Composer autoloader.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

/**
 * Bootstrap the plugin.
 *
 * This function loads the plugin bootstrap file and executes the initialization.
 * The bootstrap file returns a callable that handles plugin registration.
 *
 * @since 0.0.1
 */
call_user_func(
	function ( $bootstrap ) {
		$bootstrap( __FILE__ );
	},
	require __DIR__ . '/boot/app.php'
);
