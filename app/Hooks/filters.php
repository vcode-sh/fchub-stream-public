<?php
/**
 * WordPress filter hooks for FCHub Stream.
 *
 * Registers all WordPress filter hooks used by the FCHub Stream plugin.
 * Filters are hooks that allow you to modify data during WordPress
 * execution before it is displayed or saved to the database.
 *
 * This file serves as a central location for all filter hook registrations.
 * Filters can be added below using the $app->addFilter() method.
 *
 * @package FCHub_Stream
 * @subpackage Hooks
 * @since 1.0.0
 *
 * @var \FluentCommunity\Framework\Foundation\Application $app The application instance.
 */

// Add "Get License" link to plugin row meta (Version | By | View details section) if license is not active.
$app->addFilter(
	'plugin_row_meta',
	function ( $plugin_meta, $plugin_file, $_plugin_data ) {
		// Only add link for our plugin.
		if ( $plugin_file !== plugin_basename( FCHUB_STREAM_FILE ) ) {
			return $plugin_meta;
		}

		// Check if license manager class exists and license is active.
		if ( class_exists( 'FCHubStream\App\Services\StreamLicenseManager' ) ) {
			try {
				$license = new \FCHubStream\App\Services\StreamLicenseManager();
				if ( $license->is_active() ) {
					// License is active, don't show "Get License" link.
					return $plugin_meta;
				}
			} catch ( \Throwable $e ) {
				// If license check fails, show the link anyway.
				error_log( '[FCHub Stream] Error checking license status for plugin row meta: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		// License is not active, add "Get License" link with styling to make it stand out.
		$get_license_link = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer" style="color: #2271b1; font-weight: 600; text-decoration: underline;">%s</a>',
			esc_url( 'https://fchub.co/dashboard/my-products' ),
			esc_html__( 'Get License', 'fchub-stream' )
		);

		// Add link to the meta array.
		$plugin_meta[] = $get_license_link;

		return $plugin_meta;
	},
	10,
	3
);
