<?php
/**
 * Plugin bootstrap file.
 *
 * This file returns a callable that initializes the FCHub Stream plugin.
 * It handles:
 * - Loading the Composer autoloader
 * - Hooking into FluentCommunity
 * - Registering activation/deactivation hooks
 *
 * @package FCHub_Stream
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FCHubStream\App\Core\Application;

/**
 * Bootstrap the plugin.
 *
 * This function is returned and executed by the main plugin file.
 * It sets up all necessary hooks and initializes the application.
 *
 * @since 1.0.0
 *
 * @param string $file The main plugin file path.
 *
 * @return void
 */
return function ( $file ) {
	// Ensure autoloader is loaded before registering hooks.
	if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
		require_once __DIR__ . '/../vendor/autoload.php';
	}

	// Fix SDK symlink if relative symlink doesn't work (common in Docker environments)
	if ( defined( 'FCHUB_STREAM_DIR' ) && ! class_exists( 'FCHub\License\License_Manager' ) ) {
		$sdk_symlink = FCHUB_STREAM_DIR . 'vendor/fchub/license-sdks-php';
		
		// Check if symlink exists but points to wrong location
		if ( is_link( $sdk_symlink ) ) {
			$current_target = readlink( $sdk_symlink );
			$sdk_file = $sdk_symlink . '/src/License_Manager.php';
			
			// If relative symlink doesn't resolve, try to fix it
			if ( ! file_exists( $sdk_file ) ) {
				// Try Docker path first
				$docker_target = '/var/www/html/fchub-licenses-sdks/packages/php';
				if ( file_exists( $docker_target . '/src/License_Manager.php' ) ) {
					@unlink( $sdk_symlink ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					@symlink( $docker_target, $sdk_symlink ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				} else {
					// Try to resolve relative path
					$resolved_path = realpath( dirname( $sdk_symlink ) . '/' . $current_target );
					if ( $resolved_path && file_exists( $resolved_path . '/src/License_Manager.php' ) ) {
						@unlink( $sdk_symlink ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
						@symlink( $resolved_path, $sdk_symlink ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					}
				}
			}
		}
	}

	/**
	 * Initialize Sentry error monitoring EARLY.
	 *
	 * Initialize Sentry as early as possible to capture all errors,
	 * including those during plugin initialization. Safe to call even
	 * if Sentry is disabled - will fail silently.
	 *
	 * @since 1.0.0
	 */
	if ( class_exists( 'FCHubStream\App\Services\SentryService' ) ) {
		\FCHubStream\App\Services\SentryService::init();
	}

	/**
	 * Initialize PostHog analytics EARLY.
	 *
	 * Initialize PostHog as early as possible to capture all user events,
	 * including those during plugin initialization. Safe to call even
	 * if PostHog is disabled - will fail silently.
	 *
	 * @since 1.1.0
	 */
	if ( class_exists( 'FCHubStream\App\Services\PostHogService' ) ) {
		\FCHubStream\App\Services\PostHogService::init();

		// Register shutdown hook to flush PostHog events before script termination.
		// This ensures events are sent even if script ends unexpectedly.
		add_action(
			'shutdown',
			function () {
				if ( class_exists( 'FCHubStream\App\Services\PostHogService' ) ) {
					\FCHubStream\App\Services\PostHogService::flush();
				}
			},
			999 // High priority to run late but before other shutdown hooks.
		);
	}

	/**
	 * Initialize Tamper Detection EARLY.
	 *
	 * Initialize tamper detection as early as possible to monitor file integrity
	 * and detect bypass attempts. Safe to call even if license is not active.
	 *
	 * @since 1.0.0
	 */
	if ( class_exists( 'FCHubStream\App\Services\TamperDetection' ) ) {
		\FCHubStream\App\Services\TamperDetection::init();
	}

	/**
	 * Register video deletion hooks EARLY (before portal loads).
	 *
	 * CRITICAL: These hooks must be registered immediately because they can be
	 * triggered during post deletion, which happens outside the portal context.
	 * If we wait for 'fluent_community/portal_loaded', the hooks won't be registered
	 * when posts are deleted from admin or API.
	 *
	 * @since 1.0.0
	 */
	add_action(
		'plugins_loaded',
		function () {
			// Register video deletion handler immediately (don't wait for portal).
			if ( class_exists( 'FCHubStream\App\Hooks\Handlers\VideoDeletionHandler' ) ) {
				$video_deletion_handler = new \FCHubStream\App\Hooks\Handlers\VideoDeletionHandler();

				// Hook into feed deletion.
				// Priority 5 to run BEFORE CleanupHandler (priority 10) so we can handle comments with videos.
				add_action(
					'fluent_community/feed/before_deleted',
					array( $video_deletion_handler, 'handle_feed_deleted' ),
					5, // Run BEFORE CleanupHandler (priority 10).
					1
				);

				// Hook into comment deletion (for video comments).
				add_action(
					'fluent_community/before_comment_delete',
					array( $video_deletion_handler, 'handle_comment_deleted' ),
					10,
					1
				);

				error_log( '[FCHub Stream] Video deletion hooks registered early (plugins_loaded)' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			// CRITICAL: Register portal vars hook EARLY (before portal_loaded).
			// Hook fluent_community/portal_vars may be called BEFORE fluent_community/portal_loaded,
			// so we must register it early to ensure SDK/license are checked correctly.
			if ( class_exists( 'FCHubStream\App\Hooks\PortalIntegration\ConfigProvider' ) ) {
				$config_provider = new \FCHubStream\App\Hooks\PortalIntegration\ConfigProvider();
				$config_provider->register();
			}
		},
		20 // Priority 20 to ensure FluentCommunity classes are loaded.
	);

	/**
	 * Hook into FluentCommunity when it's fully loaded.
	 *
	 * NOTE: FluentCommunity plugin MUST be active for FCHub Stream to work.
	 * This action is fired after FluentCommunity portal is fully initialized.
	 *
	 * @since 1.0.0
	 */
	add_action(
		'fluent_community/portal_loaded',
		function ( $app ) use ( $file ) {
			// Initialize FCHub Stream Application.
			new Application( $app, $file );
		}
	);

	/**
	 * Register activation hook for database table creation.
	 *
	 * Handles plugin activation, including:
	 * - Creating database tables
	 * - Setting default options
	 * - Running any necessary setup
	 *
	 * @since 1.0.0
	 */
	register_activation_hook(
		$file,
		function ( $network_wide = false ) {
			// Ensure autoloader is loaded.
			if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
				require_once __DIR__ . '/../vendor/autoload.php';
			}
			\FCHubStream\App\Hooks\Handlers\ActivationHandler::handle( $network_wide );
		}
	);

	/**
	 * Register deactivation hook for cleanup.
	 *
	 * Handles plugin deactivation cleanup tasks if needed.
	 *
	 * @since 1.0.0
	 */
	register_deactivation_hook(
		$file,
		function ( $network_wide = false ) {
			// Ensure autoloader is loaded.
			if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
				require_once __DIR__ . '/../vendor/autoload.php';
			}
			\FCHubStream\App\Hooks\Handlers\DeactivationHandler::handle( $network_wide );
		}
	);
};
