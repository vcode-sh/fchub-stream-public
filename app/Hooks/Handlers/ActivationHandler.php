<?php
/**
 * Plugin activation handler.
 *
 * Manages plugin activation process including database schema creation,
 * multisite support, and version tracking. Handles both single-site and
 * network-wide activations.
 *
 * @package FCHub_Stream
 * @subpackage Hooks\Handlers
 * @since 1.0.0
 */

namespace FCHubStream\App\Hooks\Handlers;

use FCHubStream\App\Services\PostHogService;

/**
 * Class ActivationHandler
 *
 * Handles all plugin activation tasks including database table creation
 * and setup for both single and multisite installations.
 *
 * @package FCHub_Stream
 * @subpackage Hooks\Handlers
 * @since 1.0.0
 */
class ActivationHandler {
	/**
	 * Handle plugin activation.
	 *
	 * Main activation entry point. Handles both single-site and network-wide
	 * activations. For network activations, iterates through all sites in
	 * the network and runs activation for each.
	 *
	 * @since 1.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param bool $network_wide Whether this is a network-wide activation.
	 *
	 * @return void
	 */
	public static function handle( $network_wide = false ) {
		// Track activation event.
		// Initialize PostHog if not already done (activation hook runs early).
		if ( class_exists( 'FCHubStream\App\Services\PostHogService' ) ) {
			// Try to initialize PostHog (will only work if API key is configured).
			PostHogService::init();

			if ( PostHogService::is_initialized() ) {
				// Check if this is first activation (no config exists yet).
				$config              = get_option( 'fchub_stream_config', null );
				$is_first_activation = null === $config;

				PostHogService::track_plugin_activation( $network_wide, $is_first_activation );
				// Flush to ensure event is sent immediately.
				PostHogService::flush();
			}
		}

		if ( $network_wide ) {
			global $wpdb;
			$old_blog = $wpdb->blogid;

			// Get all blog IDs in the network.
			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				self::create_db_tables();
			}

			switch_to_blog( $old_blog );
		} else {
			self::create_db_tables();
		}
	}

	/**
	 * Create database tables.
	 *
	 * Creates necessary database tables for the plugin using dbDelta.
	 * Safe to run multiple times (idempotent). Currently a placeholder
	 * for future table creation needs.
	 *
	 * Uses dbDelta for proper table creation/updates following WordPress
	 * best practices. This allows for safe schema updates in future versions.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function create_db_tables() {
		$upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';

		if ( file_exists( $upgrade_file ) ) {
			require_once $upgrade_file;
		}

		// Database tables will be created here when needed.
		// Use dbDelta() for table creation to ensure proper updates.
		// Example:
		// global $wpdb;
		// $charset_collate = $wpdb->get_charset_collate();
		// $sql = "CREATE TABLE {$wpdb->prefix}fchub_stream_table (
		// id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		// PRIMARY KEY  (id)
		// ) $charset_collate;".
		// dbDelta( $sql ).
	}
}
