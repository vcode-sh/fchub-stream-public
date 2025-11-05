<?php
/**
 * REST API Controller for Sentry configuration.
 *
 * @package FCHub_Stream
 * @subpackage Http\Controllers
 * @since 1.0.0
 */

namespace FCHubStream\App\Http\Controllers;

use FCHubStream\App\Http\Controllers\Traits\ParsesJsonRequest;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use FCHubStream\App\Models\StreamConfig;
use FCHubStream\App\Services\SentryService;

/**
 * Sentry Configuration Controller.
 *
 * Handles REST API endpoints for managing Sentry error monitoring configuration.
 * Provides get, save, and test operations for Sentry settings.
 *
 * @since 1.0.0
 */
class SentryConfigController {

	use ParsesJsonRequest;

	/**
	 * Get Sentry configuration.
	 *
	 * Retrieves current Sentry settings (DSN and enabled status).
	 * Returns full DSN for frontend initialization (DSN is safe to expose).
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response Response object with configuration data.
	 */
	public function get( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by REST API interface.
		$config        = StreamConfig::get();
		$sentry_config = $config['sentry'] ?? array(
			'enabled' => false,
			'dsn'     => '',
		);

		// Return full DSN for frontend Sentry SDK initialization.
		// DSN is a public key (not a secret) and is safe to expose to frontend.
		return new WP_REST_Response(
			array(
				'success' => true,
				'config'  => array(
					'enabled' => ! empty( $sentry_config['enabled'] ),
					'dsn'     => $sentry_config['dsn'] ?? '',
				),
			),
			200
		);
	}

	/**
	 * Save Sentry configuration.
	 *
	 * Updates Sentry settings (DSN and enabled status).
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request object with Sentry configuration.
	 *
	 * @return WP_REST_Response|WP_Error Response with success message, or error on failure.
	 */
	public function save( WP_REST_Request $request ) {
		try {
			// Parse JSON request data using trait method.
			$data = $this->parse_json_request( $request );

			if ( empty( $data ) || ! is_array( $data ) ) {
				return new WP_Error(
					'invalid_data',
					__( 'Invalid configuration data.', 'fchub-stream' ),
					array( 'status' => 400 )
				);
			}

			// Get current config.
			$config = StreamConfig::get();

			// Update Sentry config.
			$config['sentry'] = array(
				'enabled' => ! empty( $data['enabled'] ),
				'dsn'     => isset( $data['dsn'] ) ? sanitize_text_field( $data['dsn'] ) : '',
			);

			// Save updated config.
			$saved = StreamConfig::save( $config );

			if ( ! $saved ) {
				return new WP_Error(
					'save_failed',
					__( 'Failed to save Sentry configuration.', 'fchub-stream' ),
					array( 'status' => 500 )
				);
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Sentry configuration saved successfully.', 'fchub-stream' ),
				),
				200
			);
		} catch ( \Exception $e ) {
			error_log( '[FCHub Stream] Exception in SentryConfigController::save: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			// Capture exception to Sentry (if initialized).
			SentryService::capture_exception( $e );

			return new WP_Error(
				'save_exception',
				__( 'An error occurred while saving Sentry configuration.', 'fchub-stream' ) . ' ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Test Sentry connection.
	 *
	 * Sends a test message to Sentry to verify configuration is working.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error Response with test result.
	 */
	public function test( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by REST API interface.
		try {
			$result = SentryService::test_connection();

			if ( 'error' === $result['status'] ) {
				return new WP_Error(
					'test_failed',
					$result['message'],
					array( 'status' => 400 )
				);
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => $result['message'],
				),
				200
			);
		} catch ( \Exception $e ) {
			error_log( '[FCHub Stream] Exception in SentryConfigController::test: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			return new WP_Error(
				'test_exception',
				__( 'An error occurred while testing Sentry connection.', 'fchub-stream' ) . ' ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}
}
