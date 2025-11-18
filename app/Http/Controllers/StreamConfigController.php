<?php
/**
 * REST API Controller for stream configuration.
 *
 * @package FCHub_Stream
 * @subpackage Http\Controllers
 * @since 1.0.0
 */

namespace FCHubStream\App\Http\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use FCHubStream\App\Services\StreamConfigService;
use FCHubStream\App\Services\SentryService;
use FCHubStream\App\Services\PostHogService;
use FCHubStream\App\Services\StreamLicenseManager;
use FCHubStream\App\Models\StreamConfig;
use FCHubStream\App\Http\Controllers\Traits\ParsesJsonRequest;

/**
 * Main Stream Configuration Controller.
 *
 * Handles shared configuration operations across all stream providers.
 * Manages retrieval and removal of stream configuration data.
 *
 * @since 1.0.0
 */
class StreamConfigController {
	use ParsesJsonRequest;

	/**
	 * Retrieve stream configuration.
	 *
	 * Handles GET request to fetch current stream provider settings.
	 * Returns public configuration without sensitive data like API tokens.
	 * SECURITY: Only returns provider configuration if license is active.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response Response object with configuration data.
	 *                          Response structure: {
	 *                              @type bool  $success Whether request was successful.
	 *                              @type array $config  Public configuration data (empty if license inactive).
	 *                          }
	 */
	public function get( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by REST API interface.
		// SECURITY: Check license before returning configuration.
		// If license is not active, return empty config to prevent showing provider settings.
		$license_active = false;
		if ( class_exists( 'FCHubStream\App\Services\StreamLicenseManager' ) ) {
			$license        = new StreamLicenseManager();
			$license_active = $license->is_active();
		}

		// If license is not active, return empty configuration.
		if ( ! $license_active ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'config'  => array(
						'provider'   => '',
						'cloudflare' => array(),
						'bunny'      => array(),
					),
				),
				200
			);
		}

		// License is active - return full configuration.
		$config = StreamConfigService::get_public();

		return new WP_REST_Response(
			array(
				'success' => true,
				'config'  => $config,
			),
			200
		);
	}

	/**
	 * Remove all stream configuration.
	 *
	 * Handles DELETE request to remove all stream provider configuration.
	 * Deletes all settings including API credentials and provider settings.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error Response with success message, or error on failure.
	 *                                   Response structure: {
	 *                                       @type bool   $success Whether removal was successful.
	 *                                       @type string $message Success or error message.
	 *                                   }
	 *
	 * @throws WP_Error If deletion fails or exception occurs.
	 */
	public function remove( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by REST API interface.
		try {
			$deleted = StreamConfig::delete();

			if ( ! $deleted ) {
				return new WP_Error(
					'delete_failed',
					__( 'Failed to remove configuration.', 'fchub-stream' ),
					array( 'status' => 500 )
				);
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Configuration removed successfully.', 'fchub-stream' ),
				),
				200
			);
		} catch ( \Exception $e ) {
			error_log( '[FCHub Stream] Exception in remove: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			SentryService::capture_exception( $e );
			return new WP_Error(
				'remove_exception',
				__( 'An error occurred while removing configuration.', 'fchub-stream' ) . ' ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Update active provider.
	 *
	 * Handles PATCH request to change the active stream provider.
	 * Updates the provider field in configuration.
	 * SECURITY: Requires active license.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error Response with success message, or error on failure.
	 */
	public function update_provider( WP_REST_Request $request ) {
		try {
			// Check license before allowing provider changes.
			if ( class_exists( 'FCHubStream\App\Services\StreamLicenseManager' ) ) {
				$license = new StreamLicenseManager();
				if ( ! $license->is_active() ) {
					return new WP_Error(
						'license_required',
						__( 'Active FCHub Stream license required to change stream provider.', 'fchub-stream' ),
						array( 'status' => 403 )
					);
				}
			}

			$data = $this->parse_json_request( $request );

			if ( null === $data || empty( $data ) || ! isset( $data['provider'] ) ) {
				error_log( '[FCHub Stream] update_provider: Invalid data received. Data: ' . wp_json_encode( $data ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'invalid_data',
					__( 'Provider is required.', 'fchub-stream' ),
					array( 'status' => 400 )
				);
			}

			$provider = sanitize_text_field( $data['provider'] );

			if ( ! in_array( $provider, array( 'cloudflare', 'bunny' ), true ) ) {
				return new WP_Error(
					'invalid_provider',
					__( 'Invalid provider. Must be "cloudflare" or "bunny".', 'fchub-stream' ),
					array( 'status' => 400 )
				);
			}

			// Get current config.
			$config       = StreamConfig::get();
			$old_provider = $config['provider'] ?? 'unknown';

			if ( ! is_array( $config ) ) {
				error_log( '[FCHub Stream] update_provider: Config is not an array. Config: ' . wp_json_encode( $config ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'invalid_config',
					__( 'Invalid configuration retrieved.', 'fchub-stream' ),
					array( 'status' => 500 )
				);
			}

			// Update provider.
			$config['provider'] = $provider;

			// Save updated config.
			$saved = StreamConfig::save( $config );

			if ( ! $saved ) {
				error_log( '[FCHub Stream] update_provider: Failed to save config. Config: ' . wp_json_encode( $config ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'save_failed',
					__( 'Failed to update provider.', 'fchub-stream' ),
					array( 'status' => 500 )
				);
			}

			// Track provider switch in PostHog if provider actually changed.
			try {
				if ( PostHogService::is_initialized() && $old_provider !== $provider ) {
					PostHogService::track_provider_switched( $old_provider, $provider );
				}
			} catch ( \Exception $e ) {
				// Don't fail the request if PostHog tracking fails.
				error_log( '[FCHub Stream] Failed to track provider switch: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => sprintf(
						/* translators: %s: Provider name */
						__( 'Active provider changed to %s.', 'fchub-stream' ),
						'cloudflare' === $provider ? 'Cloudflare Stream' : 'Bunny.net Stream'
					),
				),
				200
			);
		} catch ( \Exception $e ) {
			error_log( '[FCHub Stream] Exception in update_provider: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			SentryService::capture_exception( $e );
			return new WP_Error(
				'update_provider_exception',
				__( 'An error occurred while updating provider.', 'fchub-stream' ) . ' ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}
}
