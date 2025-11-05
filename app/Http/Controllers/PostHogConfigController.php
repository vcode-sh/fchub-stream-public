<?php
/**
 * REST API Controller for PostHog configuration.
 *
 * @package FCHub_Stream
 * @subpackage Http\Controllers
 * @since 1.1.0
 */

namespace FCHubStream\App\Http\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use FCHubStream\App\Services\PostHogService;

/**
 * PostHog Configuration Controller.
 *
 * Handles REST API endpoints for testing PostHog analytics connection.
 * PostHog is configured via hardcoded API key in config/app.php (developer only).
 * End users do NOT configure PostHog - it's automatic.
 *
 * @since 1.1.0
 */
class PostHogConfigController {

	/**
	 * Test PostHog connection.
	 *
	 * Sends a test event to PostHog to verify configuration is working.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error Response with test result.
	 */
	public function test( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by REST API interface.
		try {
			$result = PostHogService::test_connection();

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
			error_log( '[FCHub Stream] Exception in PostHogConfigController::test: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			return new WP_Error(
				'test_exception',
				__( 'An error occurred while testing PostHog connection.', 'fchub-stream' ) . ' ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}
}
