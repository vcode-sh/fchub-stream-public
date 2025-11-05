<?php
/**
 * Plugin Name: FCHub Stream
 * Plugin URI: https://fchub.co
 * Description: Video streaming for FluentCommunity. Direct uploads to Cloudflare Stream or Bunny.net. Built because WordPress media library and video don't mix.
 * Version: 0.0.2
 * Author: Vibe Code
 * Author URI: https://x.com/vcode_sh
 * License: Proprietary
 * License URI: https://github.com/vcode-sh/fchub-stream-public/blob/main/LICENSE
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
define( 'FCHUB_STREAM_VERSION', '0.0.2' );

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

// Initialize Plugin Update Checker for GitHub releases.
if ( class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
	$fchub_stream_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/vcode-sh/fchub-stream-public',
		__FILE__,
		'fchub-stream'
	);

	// Enable release assets to use the ZIP files from GitHub releases.
	$fchub_stream_update_checker->getVcsApi()->enableReleaseAssets();
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
