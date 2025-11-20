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
use function FCHubStream\App\Utils\log_debug;

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
	// CRITICAL: Check for broken symlink BEFORE loading Composer autoloader.
	// Composer autoloader will fail if vendor/fchub/license-sdks-php is a broken symlink.
	$sdk_symlink_path = __DIR__ . '/../vendor/fchub/license-sdks-php';
	$sdk_is_broken    = false;
	$sdk_not_found    = false;
	
	// Check if SDK path exists at all.
	if ( ! file_exists( $sdk_symlink_path ) && ! is_link( $sdk_symlink_path ) ) {
		$sdk_not_found = true;
		$sdk_is_broken = true;
	} elseif ( is_link( $sdk_symlink_path ) ) {
		// Check if symlink exists and if it's broken (target doesn't exist).
		$symlink_target = @readlink( $sdk_symlink_path ); // Suppress warning if readlink fails.
		$resolved_path  = @realpath( $sdk_symlink_path ); // Suppress warning if realpath fails.
		
		// If realpath returns false, symlink is broken.
		if ( false === $resolved_path || false === $symlink_target ) {
			$sdk_is_broken = true;
		}
	} elseif ( ! is_dir( $sdk_symlink_path ) ) {
		// Path exists but is not a directory (and not a symlink).
		$sdk_not_found = true;
		$sdk_is_broken = true;
	}
	
	// If SDK is broken or not found, remove it from Composer autoloader.
	if ( $sdk_is_broken ) {
		// CRITICAL: Remove FCHub\License namespace from BOTH Composer autoloader files BEFORE loading.
		// Composer uses both autoload_psr4.php and autoload_static.php (cached).
		$vendor_dir = __DIR__ . '/../vendor/composer';
		
		// Remove from autoload_psr4.php
		$autoload_psr4_file = $vendor_dir . '/autoload_psr4.php';
		if ( file_exists( $autoload_psr4_file ) ) {
			$autoload_psr4_content = file_get_contents( $autoload_psr4_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			// Remove FCHub\License namespace entry.
			$autoload_psr4_content = preg_replace(
				"/\s*'FCHub\\\\License\\\\'\s*=>\s*array\([^)]+\),?\s*/",
				'',
				$autoload_psr4_content
			);
			file_put_contents( $autoload_psr4_file, $autoload_psr4_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		}
		
		// Remove from autoload_static.php (cached version)
		$autoload_static_file = $vendor_dir . '/autoload_static.php';
		if ( file_exists( $autoload_static_file ) ) {
			$autoload_static_content = file_get_contents( $autoload_static_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			// Remove from $prefixLengthsPsr4 array (line 46)
			$autoload_static_content = preg_replace(
				"/\s*'FCHub\\\\License\\\\'\s*=>\s*\d+,?\s*/",
				'',
				$autoload_static_content
			);
			// Remove from $prefixDirsPsr4 array (entire entry with array)
			$autoload_static_content = preg_replace(
				"/\s*'FCHub\\\\License\\\\'\s*=>\s*array\s*\([^)]+\),?\s*/",
				'',
				$autoload_static_content
			);
			// Remove from $classMap array (all FCHub\License classes, lines 160-163)
			$autoload_static_content = preg_replace(
				"/\s*'FCHub\\\\License\\\\[^']+'\s*=>\s*__DIR__\s*\.\s*'\/\.\.'\s*\.\s*'\/fchub\/license-sdks-php\/[^']+',?\s*/",
				'',
				$autoload_static_content
			);
			file_put_contents( $autoload_static_file, $autoload_static_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		}
		
		// Remove from autoload_classmap.php
		$autoload_classmap_file = $vendor_dir . '/autoload_classmap.php';
		if ( file_exists( $autoload_classmap_file ) ) {
			$autoload_classmap_content = file_get_contents( $autoload_classmap_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			// Remove all FCHub\License class mappings
			$autoload_classmap_content = preg_replace(
				"/\s*'FCHub\\\\License\\\\[^']+'\s*=>\s*\$vendorDir\s*\.\s*'\/fchub\/license-sdks-php\/[^']+',?\s*/",
				'',
				$autoload_classmap_content
			);
			file_put_contents( $autoload_classmap_file, $autoload_classmap_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		}
		
		// Log warning but don't break plugin - license features will be disabled.
		if ( function_exists( 'log_debug' ) ) {
			if ( $sdk_not_found ) {
				log_debug( 'License SDK not found at: ' . $sdk_symlink_path );
			} else {
				$symlink_target_display = isset( $symlink_target ) ? $symlink_target : 'unknown';
				log_debug( 'License SDK symlink is broken. Target: ' . $symlink_target_display );
			}
			log_debug( 'License features will be disabled. Please ensure SDK package is included in release.' );
		}
	}

	// CRITICAL: Register custom autoloader for SDK BEFORE Composer autoloader.
	// This prevents Composer from trying to load classes through broken symlinks.
	// Use high priority (prepend) to run before Composer autoloader.
	spl_autoload_register(
		function ( $class ) use ( $sdk_is_broken ) {
			// Only handle FCHub\License namespace.
			if ( 0 !== strpos( $class, 'FCHub\\License\\' ) ) {
				return false;
			}

			// If SDK symlink is broken, suppress error and return false (graceful degradation).
			// This prevents Composer autoloader from trying to load through broken symlink.
			if ( $sdk_is_broken ) {
				// Suppress any warnings/errors and return false to let other autoloaders handle it.
				return false;
			}

			// Convert namespace to file path.
			$relative_class = substr( $class, strlen( 'FCHub\\License\\' ) );
			$file_path      = str_replace( '\\', '/', $relative_class ) . '.php';

			// Try multiple SDK paths.
			$sdk_base_paths = array(
				__DIR__ . '/../vendor/fchub/license-sdks-php',
				defined( 'FCHUB_STREAM_DIR' ) ? FCHUB_STREAM_DIR . 'vendor/fchub/license-sdks-php' : null,
			);

			foreach ( $sdk_base_paths as $sdk_base ) {
				if ( null === $sdk_base ) {
					continue;
				}

				$full_path = $sdk_base . '/src/' . $file_path;

				// Try to resolve symlink, but check original path if resolution fails.
				$resolved_path = realpath( $full_path );
				$check_path    = ( false !== $resolved_path ) ? $resolved_path : $full_path;

				// Check if file exists and is readable.
				if ( file_exists( $check_path ) && is_readable( $check_path ) ) {
					require_once $check_path;
					return true;
				}
			}

			return false;
		},
		true, // Prepend to autoload stack (run before Composer autoloader).
		true  // Throw exception if class not found (let Composer handle it).
	);

	// CRITICAL: Suppress warnings from Composer autoloader if SDK is broken.
	// This prevents "Failed to open stream" warnings when SDK package is missing.
	$original_error_reporting = null;
	$error_handler            = null;
	
	if ( $sdk_is_broken ) {
		// Start output buffering to catch any warnings/errors.
		ob_start();
		
		// Temporarily disable error reporting for warnings/notices.
		$original_error_reporting = error_reporting();
		error_reporting( $original_error_reporting & ~E_WARNING & ~E_NOTICE );
		
		// Set error handler to suppress SDK-related warnings.
		$error_handler = set_error_handler(
			function ( $errno, $errstr, $errfile, $errline ) use ( &$error_handler ) {
				// Suppress ALL warnings/notices about missing SDK files from Composer autoloader.
				if ( ( E_WARNING === $errno || E_NOTICE === $errno ) &&
					( strpos( $errstr, 'fchub/license-sdks-php' ) !== false ||
					  strpos( $errstr, 'Failed to open stream' ) !== false ||
					  strpos( $errstr, 'Failed opening' ) !== false ||
					  strpos( $errfile, 'ClassLoader.php' ) !== false ) ) {
					// Suppress this specific error - SDK is intentionally missing.
					return true;
				}
				
				// Call previous error handler for other errors.
				if ( null !== $error_handler && is_callable( $error_handler ) ) {
					return call_user_func( $error_handler, $errno, $errstr, $errfile, $errline );
				}
				
				return false;
			},
			E_WARNING | E_NOTICE
		);
	}

	// Ensure autoloader is loaded before registering hooks.
	// Use @ to suppress any warnings if error handler didn't catch them.
	if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
		if ( $sdk_is_broken ) {
			// Suppress warnings during autoloader loading.
			@require_once __DIR__ . '/../vendor/autoload.php'; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} else {
			require_once __DIR__ . '/../vendor/autoload.php';
		}
	}
	
	// Clean output buffer, restore error reporting and error handler after autoloader is loaded.
	if ( $sdk_is_broken ) {
		ob_end_clean(); // Discard any warnings that were buffered.
		if ( null !== $original_error_reporting ) {
			error_reporting( $original_error_reporting );
		}
		if ( null !== $error_handler ) {
			restore_error_handler();
		}
	}

	// CRITICAL: Ensure SDK is loaded before StreamLicenseManager is used.
	// StreamLicenseManager extends FCHub\License\License_Manager, so the parent class
	// must be available when PHP tries to load StreamLicenseManager.
	if ( defined( 'FCHUB_STREAM_DIR' ) && ! class_exists( 'FCHub\License\License_Manager' ) ) {
		// Try multiple paths to find SDK.
		$sdk_paths = array(
			FCHUB_STREAM_DIR . 'vendor/fchub/license-sdks-php/src/License_Manager.php',
			__DIR__ . '/../vendor/fchub/license-sdks-php/src/License_Manager.php',
		);

		foreach ( $sdk_paths as $sdk_path ) {
			// Use realpath to resolve symlinks, but check original path if realpath fails.
			$resolved_sdk_path = realpath( $sdk_path );
			$check_path        = ( false !== $resolved_sdk_path ) ? $resolved_sdk_path : $sdk_path;
			
			if ( file_exists( $check_path ) && is_readable( $check_path ) ) {
				require_once $check_path;
				// Also try to load autoloader from SDK if it exists.
				$sdk_dir       = dirname( dirname( $check_path ) );
				$sdk_autoload  = $sdk_dir . '/vendor/autoload.php';
				if ( file_exists( $sdk_autoload ) ) {
					require_once $sdk_autoload;
				}
				break;
			}
		}
	}

	// Note: SDK is now included as full copy in vendor/ (not symlink).
	// Composer autoloader should handle loading automatically.
	// If class still doesn't exist after autoloader, log error for debugging.
	if ( defined( 'FCHUB_STREAM_DIR' ) && ! class_exists( 'FCHub\License\License_Manager' ) ) {
		$sdk_file = FCHUB_STREAM_DIR . 'vendor/fchub/license-sdks-php/src/License_Manager.php';
		if ( ! file_exists( $sdk_file ) ) {
			// Log error but don't break plugin - license features will be disabled.
			if ( function_exists( 'log_debug' ) ) {
				log_debug( 'License SDK not found at: ' . $sdk_file );
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

				// Hook into feed update filter to capture old meta before update.
				add_filter(
					'fluent_community/feed/update_data',
					array( $video_deletion_handler, 'capture_feed_meta_before_update' ),
					10,
					2
				);

				// Hook into feed update action to detect and delete removed videos.
				add_action(
					'fluent_community/feed/updated',
					array( $video_deletion_handler, 'handle_feed_updated' ),
					10,
					2
				);

				// Hook into comment update filter to capture old meta before update.
				add_filter(
					'fluent_community/comment/update_comment_data',
					array( $video_deletion_handler, 'capture_comment_meta_before_update' ),
					10,
					3
				);

				// Hook into comment update action to detect and delete removed videos.
				add_action(
					'fluent_community/comment_updated',
					array( $video_deletion_handler, 'handle_comment_updated' ),
					10,
					2
				);

				log_debug( 'Video deletion hooks registered early (plugins_loaded)' );
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
