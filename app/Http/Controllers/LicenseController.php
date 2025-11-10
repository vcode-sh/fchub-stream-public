<?php
/**
 * REST API Controller for license management.
 *
 * Handles license activation, validation, deactivation, and status retrieval.
 *
 * @package FCHubStream
 * @subpackage Http\Controllers
 * @since 1.0.0
 */

namespace FCHubStream\App\Http\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use FCHubStream\App\Services\StreamLicenseManager;
use FCHubStream\App\Services\SentryService;
use FCHubStream\App\Services\PostHogService;

/**
 * License Controller class.
 *
 * Provides REST API endpoints for license management operations.
 *
 * @since 1.0.0
 */
class LicenseController {

	/**
	 * Get license status.
	 *
	 * Returns current license status including activation state and features.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response Response with license status.
	 */
	public function get_status( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by REST API interface.
		try {
			// Ensure autoloader is loaded (should already be loaded in bootstrap, but double-check)
			if ( ! class_exists( 'FCHub\License\License_Manager' ) ) {
				// Try to load autoloader if not already loaded
				$plugin_dir = defined( 'FCHUB_STREAM_DIR' ) ? FCHUB_STREAM_DIR : dirname( dirname( dirname( __DIR__ ) ) );
				$autoload_path = rtrim( $plugin_dir, '/' ) . '/vendor/autoload.php';
				
				error_log( '[FCHub Stream] License SDK check - Plugin dir: ' . $plugin_dir ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[FCHub Stream] License SDK check - Autoload path: ' . $autoload_path ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[FCHub Stream] License SDK check - Autoload exists: ' . ( file_exists( $autoload_path ) ? 'YES' : 'NO' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[FCHub Stream] License SDK check - Class exists before require: ' . ( class_exists( 'FCHub\License\License_Manager' ) ? 'YES' : 'NO' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				
				if ( file_exists( $autoload_path ) ) {
					require_once $autoload_path;
					error_log( '[FCHub Stream] License SDK check - Autoloader loaded' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( '[FCHub Stream] License SDK check - Class exists after require: ' . ( class_exists( 'FCHub\License\License_Manager' ) ? 'YES' : 'NO' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				} else {
					error_log( '[FCHub Stream] License SDK check - Autoloader file not found!' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				
				// Final check - if still not available, return error with detailed info
				if ( ! class_exists( 'FCHub\License\License_Manager' ) ) {
					$sdk_path = rtrim( $plugin_dir, '/' ) . '/vendor/fchub/license-sdks-php';
					$sdk_exists = file_exists( $sdk_path ) || is_link( $sdk_path );
					error_log( '[FCHub Stream] License SDK not available after autoload check' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( '[FCHub Stream] SDK path: ' . $sdk_path ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( '[FCHub Stream] SDK exists: ' . ( $sdk_exists ? 'YES' : 'NO' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					
					if ( $sdk_exists ) {
						$real_path = realpath( $sdk_path );
						error_log( '[FCHub Stream] SDK real path: ' . ( $real_path ?: 'NOT RESOLVABLE' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					}
					
					return new WP_Error(
						'sdk_not_available',
						__( 'License SDK not available. Please ensure composer dependencies are installed.', 'fchub-stream' ),
						array( 
							'status' => 500,
							'debug_info' => array(
								'autoload_path' => $autoload_path,
								'autoload_exists' => file_exists( $autoload_path ),
								'sdk_path' => $sdk_path,
								'sdk_exists' => $sdk_exists,
							)
						)
					);
				}
			}

			if ( ! class_exists( 'FCHubStream\App\Services\StreamLicenseManager' ) ) {
				error_log( '[FCHub Stream] StreamLicenseManager class not found' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'license_manager_not_found',
					__( 'License manager class not found.', 'fchub-stream' ),
					array( 'status' => 500 )
				);
			}

			$license = new StreamLicenseManager();

			if ( ! $license->is_active() ) {
				return new WP_REST_Response(
					array(
						'success' => true,
						'active'  => false,
						'message' => __( 'No license activated.', 'fchub-stream' ),
					),
					200
				);
			}

			// Validate license status (check expiration, etc.)
			$validation_result = $license->validate_license();
			if ( is_wp_error( $validation_result ) ) {
				error_log( '[FCHub Stream] License validation failed: ' . $validation_result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_REST_Response(
					array(
						'success' => true,
						'active'  => false,
						'message' => $validation_result->get_error_message(),
						'error_code' => $validation_result->get_error_code(),
					),
					200
				);
			}

			$features = $license->get_features();
			// Get license data directly from storage (same instance used by manager)
			if ( ! class_exists( 'FCHub\License\License_Storage' ) ) {
				error_log( '[FCHub Stream] License_Storage class not found.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'storage_not_found',
					__( 'License storage class not found.', 'fchub-stream' ),
					array( 'status' => 500 )
				);
			}

			$storage = new \FCHub\License\License_Storage( 'fchub-stream' );
			$license_data = $storage->get();

			if ( ! $license_data ) {
				return new WP_REST_Response(
					array(
						'success' => true,
						'active'  => false,
						'message' => __( 'No license activated.', 'fchub-stream' ),
					),
					200
				);
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'active'  => true,
					'license' => array(
						'key'        => $license_data['key'] ?? '',
						'plan'       => $license_data['plan'] ?? '',
						'expires_at' => $license_data['expires_at'] ?? '',
						'features'   => $features,
					),
				),
				200
			);
		} catch ( \Throwable $e ) {
			$error_message = $e->getMessage();
			$error_trace = $e->getTraceAsString();
			error_log( '[FCHub Stream] Exception in get_status: ' . $error_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[FCHub Stream] Stack trace: ' . $error_trace ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			
			if ( class_exists( 'FCHubStream\App\Services\SentryService' ) ) {
				SentryService::capture_exception( $e );
			}
			
			return new WP_Error(
				'license_status_error',
				sprintf(
					// translators: %s: Error message
					__( 'Failed to retrieve license status: %s', 'fchub-stream' ),
					$error_message
				),
				array( 
					'status' => 500,
					'error_details' => $error_message,
				)
			);
		}
	}

	/**
	 * Activate license.
	 *
	 * Activates a license key for this WordPress site.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request object with license_key.
	 *
	 * @return WP_REST_Response|WP_Error Response with activation result or error.
	 */
	public function activate( WP_REST_Request $request ) {
		try {
			error_log( '[FCHub Stream] Activate: Starting activation...' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			
			// Parse JSON body with fallbacks (like VideoUploadController)
			$data = $request->get_json_params();
			if ( empty( $data ) || ! is_array( $data ) ) {
				$raw_body = file_get_contents( 'php://input' );
				if ( ! empty( $raw_body ) ) {
					$decoded = json_decode( $raw_body, true );
					if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) && ! empty( $decoded ) ) {
						$data = $decoded;
					}
				}
			}
			if ( empty( $data ) || ! is_array( $data ) ) {
				$data = $request->get_params();
			}
			
			error_log( '[FCHub Stream] Activate: Request data: ' . wp_json_encode( $data ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			
			$license_key = $data['license_key'] ?? $request->get_param( 'license_key' );
			error_log( '[FCHub Stream] Activate: License key received: ' . ( $license_key ? 'YES (' . substr( $license_key, 0, 20 ) . '...)' : 'NO' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			if ( empty( $license_key ) ) {
				error_log( '[FCHub Stream] Activate: Missing license key. All params: ' . wp_json_encode( $request->get_params() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'missing_license_key',
					__( 'License key is required.', 'fchub-stream' ),
					array( 'status' => 400 )
				);
			}

			error_log( '[FCHub Stream] Activate: Creating StreamLicenseManager...' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			$license = new StreamLicenseManager();
			error_log( '[FCHub Stream] Activate: Calling activate_license()...' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			$result  = $license->activate_license( $license_key );

			if ( is_wp_error( $result ) ) {
				error_log( '[FCHub Stream] Activate: SDK returned error - Code: ' . $result->get_error_code() . ', Message: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[FCHub Stream] Activate: Error data: ' . wp_json_encode( $result->get_error_data() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				
				// Track license activation failure in PostHog
				PostHogService::capture_event(
					'license_activation_failed',
					array(
						'error_code' => $result->get_error_code(),
						'error_message' => $result->get_error_message(),
						'license_key_prefix' => substr( $license_key, 0, 15 ) . '...', // Partial key for tracking
					)
				);
				
				// Track license activation failure in Sentry
				SentryService::capture_message(
					'License activation failed: ' . $result->get_error_message() . ' (Code: ' . $result->get_error_code() . ')',
					'warning'
				);
				SentryService::add_breadcrumb(
					'License activation failed',
					'license.activation',
					'warning',
					array(
						'error_code' => $result->get_error_code(),
						'error_message' => $result->get_error_message(),
						'license_key_prefix' => substr( $license_key, 0, 15 ) . '...',
					)
				);
				
				return new WP_Error(
					$result->get_error_code(),
					$result->get_error_message(),
					array( 
						'status' => 400,
						'error_data' => $result->get_error_data(),
					)
				);
			}

			error_log( '[FCHub Stream] Activate: Success - ' . wp_json_encode( $result ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			// Handle response format: Stream uses features object, Companion uses direct fields
			$license_response = $result['license'] ?? array();
			$features = isset( $license_response['features'] )
				? $license_response['features']  // Stream format (features object)
				: array();                       // Companion format (not used for Stream)
			
			$plan = $license_response['plan'] ?? 'unknown';
			$expires_at = $license_response['expires_at'] ?? '';
			
			// Track successful license activation in PostHog
			PostHogService::capture_event(
				'license_activated',
				array(
					'plan' => $plan,
					'expires_at' => $expires_at,
					'has_features' => ! empty( $features ),
					'features_count' => is_array( $features ) ? count( $features ) : 0,
					'license_key_prefix' => substr( $license_key, 0, 15 ) . '...', // Partial key for tracking
				)
			);
			
			// Track successful license activation in Sentry (as breadcrumb for context)
			SentryService::add_breadcrumb(
				'License activated successfully',
				'license.activation',
				'info',
				array(
					'plan' => $plan,
					'expires_at' => $expires_at,
					'has_features' => ! empty( $features ),
				)
			);

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'License activated successfully.', 'fchub-stream' ),
					'license' => array(
						'key'       => $license_response['key'] ?? $license_key,
						'plan'      => $plan,
						'expires_at' => $expires_at,
						'features'  => $features,
					),
				),
				200
			);
		} catch ( \Exception $e ) {
			error_log( '[FCHub Stream] Exception in activate: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			SentryService::capture_exception( $e );
			return new WP_Error(
				'license_activation_error',
				__( 'Failed to activate license.', 'fchub-stream' ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Validate license.
	 *
	 * Validates the currently activated license with the API.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error Response with validation result or error.
	 */
	public function validate( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by REST API interface.
		try {
			error_log( '[FCHub Stream] Validate: Starting validation...' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			
			$license = new StreamLicenseManager();

			if ( ! $license->is_active() ) {
				error_log( '[FCHub Stream] Validate: No license active.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'no_license',
					__( 'No license activated.', 'fchub-stream' ),
					array( 'status' => 400 )
				);
			}

			error_log( '[FCHub Stream] Validate: Calling validate_license()...' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			$result = $license->validate_license();

			if ( is_wp_error( $result ) ) {
				error_log( '[FCHub Stream] Validate: SDK returned error - Code: ' . $result->get_error_code() . ', Message: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[FCHub Stream] Validate: Error data: ' . wp_json_encode( $result->get_error_data() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				
				// Track license validation failure in PostHog
				PostHogService::capture_event(
					'license_validation_failed',
					array(
						'error_code' => $result->get_error_code(),
						'error_message' => $result->get_error_message(),
					)
				);
				
				// Track license validation failure in Sentry
				SentryService::capture_message(
					'License validation failed: ' . $result->get_error_message() . ' (Code: ' . $result->get_error_code() . ')',
					'warning'
				);
				SentryService::add_breadcrumb(
					'License validation failed',
					'license.validation',
					'warning',
					array(
						'error_code' => $result->get_error_code(),
						'error_message' => $result->get_error_message(),
					)
				);
				
				return new WP_Error(
					$result->get_error_code(),
					$result->get_error_message(),
					array( 
						'status' => 400,
						'error_data' => $result->get_error_data(),
					)
				);
			}

			error_log( '[FCHub Stream] Validate: Success - ' . wp_json_encode( $result ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			// Handle response format: Stream uses features object, Companion uses direct fields
			$license_response = $result['license'] ?? array();
			$features = isset( $license_response['features'] )
				? $license_response['features']  // Stream format (features object)
				: array();                       // Companion format (not used for Stream)
			
			$plan = $license_response['plan'] ?? 'unknown';
			$expires_at = $license_response['expires_at'] ?? '';
			
			// Track successful license validation in PostHog
			PostHogService::capture_event(
				'license_validated',
				array(
					'plan' => $plan,
					'expires_at' => $expires_at,
					'has_features' => ! empty( $features ),
					'features_count' => is_array( $features ) ? count( $features ) : 0,
				)
			);
			
			// Track successful license validation in Sentry (as breadcrumb for context)
			SentryService::add_breadcrumb(
				'License validated successfully',
				'license.validation',
				'info',
				array(
					'plan' => $plan,
					'expires_at' => $expires_at,
					'has_features' => ! empty( $features ),
				)
			);

			return new WP_REST_Response(
				array(
					'success' => true,
					'valid'   => true,
					'message' => __( 'License is valid.', 'fchub-stream' ),
					'license' => array(
						'key'       => $license_response['key'] ?? '',
						'plan'      => $plan,
						'expires_at' => $expires_at,
						'features'  => $features,
					),
				),
				200
			);
		} catch ( \Exception $e ) {
			error_log( '[FCHub Stream] Exception in validate: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			SentryService::capture_exception( $e );
			return new WP_Error(
				'license_validation_error',
				__( 'Failed to validate license.', 'fchub-stream' ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Deactivate license.
	 *
	 * Deactivates the currently activated license for this site.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error Response with deactivation result or error.
	 */
	public function deactivate( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by REST API interface.
		try {
			$license = new StreamLicenseManager();

			if ( ! $license->is_active() ) {
				error_log( '[FCHub Stream] Deactivate: No license active.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'no_license',
					__( 'No license activated.', 'fchub-stream' ),
					array( 'status' => 400 )
				);
			}

			error_log( '[FCHub Stream] Deactivate: Calling deactivate_license()...' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			$result = $license->deactivate_license();

			if ( is_wp_error( $result ) ) {
				error_log( '[FCHub Stream] Deactivate: SDK returned error - Code: ' . $result->get_error_code() . ', Message: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[FCHub Stream] Deactivate: Error data: ' . wp_json_encode( $result->get_error_data() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				
				// Track license deactivation failure in PostHog
				PostHogService::capture_event(
					'license_deactivation_failed',
					array(
						'error_code' => $result->get_error_code(),
						'error_message' => $result->get_error_message(),
					)
				);
				
				// Track license deactivation failure in Sentry
				SentryService::capture_message(
					'License deactivation failed: ' . $result->get_error_message() . ' (Code: ' . $result->get_error_code() . ')',
					'warning'
				);
				SentryService::add_breadcrumb(
					'License deactivation failed',
					'license.deactivation',
					'warning',
					array(
						'error_code' => $result->get_error_code(),
						'error_message' => $result->get_error_message(),
					)
				);
				
				return new WP_Error(
					$result->get_error_code(),
					$result->get_error_message(),
					array( 
						'status' => 400,
						'error_data' => $result->get_error_data(),
					)
				);
			}

			error_log( '[FCHub Stream] Deactivate: Success - ' . wp_json_encode( $result ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			
			// Track successful license deactivation in PostHog
			PostHogService::capture_event(
				'license_deactivated',
				array()
			);
			
			// Track successful license deactivation in Sentry (as breadcrumb for context)
			SentryService::add_breadcrumb(
				'License deactivated successfully',
				'license.deactivation',
				'info',
				array()
			);

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'License deactivated successfully.', 'fchub-stream' ),
				),
				200
			);
		} catch ( \Exception $e ) {
			error_log( '[FCHub Stream] Exception in deactivate: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			SentryService::capture_exception( $e );
			return new WP_Error(
				'license_deactivation_error',
				__( 'Failed to deactivate license.', 'fchub-stream' ),
				array( 'status' => 500 )
			);
		}
	}
}

