<?php
/**
 * Plugin deactivation handler.
 *
 * Handles cleanup on plugin deactivation for both single-site and
 * network-wide installations. Does not remove data to allow reactivation
 * without data loss - only clears temporary data like transients.
 *
 * @package FCHub_Stream
 * @subpackage Hooks\Handlers
 * @since 1.0.0
 */

namespace FCHubStream\App\Hooks\Handlers;

use FCHubStream\App\Services\PostHogService;

/**
 * Class DeactivationHandler
 *
 * Manages plugin deactivation cleanup including transient removal
 * and temporary data cleanup for both single and multisite installations.
 *
 * @package FCHub_Stream
 * @subpackage Hooks\Handlers
 * @since 1.0.0
 */
class DeactivationHandler {
	/**
	 * Handle plugin deactivation.
	 *
	 * Main deactivation entry point. Handles both single-site and network-wide
	 * deactivations. For network deactivations, iterates through all sites in
	 * the network and runs cleanup for each.
	 *
	 * Does NOT delete user data, settings, or database tables to preserve
	 * data on reactivation. Only clears temporary data like transients.
	 *
	 * @since 1.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param bool $network_wide Whether this is a network-wide deactivation.
	 *
	 * @return void
	 */
	public static function handle( $network_wide = false ) {
		// Track deactivation event.
		// Initialize PostHog if not already done (deactivation hook runs early).
		if ( class_exists( 'FCHubStream\App\Services\PostHogService' ) ) {
			// Try to initialize PostHog (will only work if API key is configured).
			PostHogService::init();

			if ( PostHogService::is_initialized() ) {
				PostHogService::track_plugin_deactivation( $network_wide );
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
				self::cleanup();
			}

			switch_to_blog( $old_blog );
		} else {
			self::cleanup();
		}
	}

	/**
	 * Perform cleanup tasks.
	 *
	 * Executes all cleanup operations for the plugin including
	 * clearing transients and cached data. Does not remove permanent
	 * data like settings or database tables.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function cleanup() {
		// Clear any transients or cached data.
		self::clear_transients();
	}

	/**
	 * Clear plugin transients.
	 *
	 * Removes all fchub-stream related transients from the database.
	 * Handles both regular transients and site transients (for multisite).
	 *
	 * This cleanup ensures no temporary cached data persists after
	 * deactivation, but preserves all permanent plugin data and settings.
	 *
	 * @since 1.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return void
	 */
	private static function clear_transients() {
		global $wpdb;

		// Clear any fchub-stream related transients.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_fchub_stream_%'
			OR option_name LIKE '_transient_timeout_fchub_stream_%'"
		);

		// Clear any fchub-stream related site transients (for multisite).
		if ( is_multisite() ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				"DELETE FROM {$wpdb->sitemeta}
				WHERE meta_key LIKE '_site_transient_fchub_stream_%'
				OR meta_key LIKE '_site_transient_timeout_fchub_stream_%'"
			);
		}
	}
}
