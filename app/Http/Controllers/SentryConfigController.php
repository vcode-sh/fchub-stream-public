<?php
/**
 * REST API Controller for Sentry configuration.
 *
 * @package FCHub_Stream
 * @subpackage Http\Controllers
 * @since 1.0.0
 */

namespace FCHubStream\App\Http\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use FCHubStream\App\Services\SentryService;

/**
 * Sentry Configuration Controller.
 *
 * Handles REST API endpoints for Sentry error monitoring.
 * Provides get and test operations for Sentry settings.
 *
 * NOTE: Sentry configuration is hardcoded in config/app.php (developer-only).
 * End users do NOT configure Sentry - it's automatic. This controller is mainly
 * for testing/debugging purposes during development.
 *
 * @since 1.0.0
 */
class SentryConfigController {

	/**
	 * Get Sentry configuration.
	 *
	 * Retrieves current Sentry settings from hardcoded config/app.php.
	 * Returns DSN and enabled status for frontend initialization.
	 * DSN is safe to expose to frontend (it's a public key, not a secret).
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response Response object with configuration data.
	 */
	public function get( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by REST API interface.
		// Read hardcoded config from config/app.php (developer-only, not user-configurable).
		$app_config = include FCHUB_STREAM_DIR . 'config/app.php';
		$dsn        = $app_config['sentry_dsn'] ?? '';
		$enabled    = ! empty( $dsn );

		// Get traces_sample_rate from hardcoded config if set.
		$traces_sample_rate = null;
		if ( isset( $app_config['sentry_traces_sample_rate'] ) && null !== $app_config['sentry_traces_sample_rate'] ) {
			$traces_sample_rate = (float) $app_config['sentry_traces_sample_rate'];
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'config'  => array(
					'enabled'            => $enabled,
					'dsn'                => $dsn,
					'traces_sample_rate' => $traces_sample_rate,
				),
			),
			200
		);
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
