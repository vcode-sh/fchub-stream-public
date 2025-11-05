<?php
/**
 * Video Upload Controller
 *
 * Handles REST API endpoints for video upload, status checking, and webhook processing.
 * Provides interface for FluentCommunity Portal to upload videos directly.
 *
 * @package FCHubStream
 * @subpackage Http\Controllers
 * @since 1.0.0
 */

namespace FCHubStream\App\Http\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use FCHubStream\App\Services\VideoUploadService;
use FCHubStream\App\Services\SentryService;

/**
 * Video Upload Controller class.
 *
 * Manages video upload REST API endpoints including upload, status checking,
 * and webhook handling for Cloudflare Stream and Bunny.net Stream.
 *
 * @since 1.0.0
 */
class VideoUploadController {

	/**
	 * Upload video file
	 *
	 * Handles POST /stream/video-upload endpoint.
	 * Accepts multipart/form-data with video file and uploads to configured provider.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST API request object.
	 *
	 * @return WP_REST_Response|WP_Error Response with upload result or error.
	 */
	public function upload( WP_REST_Request $request ) {
		SentryService::add_breadcrumb(
			'Upload endpoint called',
			'http',
			'info',
			array(
				'method'       => $request->get_method(),
				'route'        => $request->get_route(),
				'user_id'      => get_current_user_id(),
				'is_logged_in' => is_user_logged_in(),
			)
		);

		error_log( '[FCHub Stream] VideoUploadController::upload() - START' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[FCHub Stream] Request method: ' . $request->get_method() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[FCHub Stream] Request route: ' . $request->get_route() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[FCHub Stream] User logged in: ' . ( is_user_logged_in() ? 'YES' : 'NO' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		// Check permissions - must be logged in user.
		// PortalPolicy may not work correctly with FluentCommunity router, so check directly.
		if ( ! is_user_logged_in() ) {
			SentryService::add_breadcrumb(
				'Upload rejected: User not logged in',
				'http',
				'warning'
			);

			error_log( '[FCHub Stream] Upload rejected: User not logged in' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new WP_Error(
				'unauthorized',
				__( 'You must be logged in to upload videos.', 'fchub-stream' ),
				array( 'status' => 401 )
			);
		}

		// Verify nonce for CSRF protection.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		error_log( '[FCHub Stream] Nonce present: ' . ( $nonce ? 'YES' : 'NO' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			error_log( '[FCHub Stream] Upload rejected: Invalid nonce' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new WP_Error(
				'invalid_nonce',
				__( 'Invalid security token. Please refresh the page and try again.', 'fchub-stream' ),
				array( 'status' => 403 )
			);
		}

		error_log( '[FCHub Stream] Security checks passed' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		// Get uploaded file.
		// WordPress REST API handles file uploads differently - check $_FILES directly.
		$files = $request->get_file_params();

		// Fallback to $_FILES if get_file_params() doesn't work.
		if ( empty( $files ) || ! isset( $files['file'] ) ) {
			$files = $_FILES;
		}

		$file = $files['file'] ?? null;

		// Log for debugging.
		error_log( '[FCHub Stream] Upload request received. Files keys: ' . wp_json_encode( array_keys( $files ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		if ( $file ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				'[FCHub Stream] File data: ' . wp_json_encode(
					array(
						'name'     => $file['name'] ?? 'not set',
						'type'     => $file['type'] ?? 'not set',
						'size'     => $file['size'] ?? 'not set',
						'tmp_name' => isset( $file['tmp_name'] ) ? $file['tmp_name'] : 'not set',
						'error'    => $file['error'] ?? 'not set',
					)
				)
			);
		}

		if ( ! $file || ! isset( $file['tmp_name'] ) ) {
			error_log( '[FCHub Stream] No file found in request' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new WP_Error(
				'no_file',
				__( 'No file uploaded.', 'fchub-stream' ),
				array( 'status' => 400 )
			);
		}

		// WordPress REST API doesn't use is_uploaded_file() validation.
		// Instead, check if file exists and has valid error code.
		if ( ! file_exists( $file['tmp_name'] ) ) {
			error_log( '[FCHub Stream] File temp path does not exist: ' . $file['tmp_name'] ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new WP_Error(
				'file_not_found',
				__( 'Uploaded file not found.', 'fchub-stream' ),
				array( 'status' => 400 )
			);
		}

		// Check for upload errors.
		if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== $file['error'] ) {
			$error_code = $file['error'] ?? 'unknown';
			error_log( '[FCHub Stream] Upload error code: ' . $error_code ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new WP_Error(
				'upload_error',
				$this->get_upload_error_message( $file['error'] ?? UPLOAD_ERR_NO_FILE ),
				array( 'status' => 400 )
			);
		}

		error_log( '[FCHub Stream] File passed validation, proceeding to upload service' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		// Get context from request (post or comment).
		$context = $request->get_param( 'context' );
		$context = sanitize_text_field( $context );

		// Default to 'post' if not specified.
		if ( empty( $context ) || ! in_array( $context, array( 'post', 'comment' ), true ) ) {
			$context = 'post';
		}

		error_log( '[FCHub Stream] Upload context: ' . $context ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		// Check if video in comments is enabled (if context is comment).
		if ( 'comment' === $context ) {
			if ( ! \FCHubStream\App\Services\StreamConfigService::is_comment_video_enabled() ) {
				error_log( '[FCHub Stream] Upload rejected: Video in comments is disabled' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'comment_video_disabled',
					__( 'Video uploads in comments are currently disabled.', 'fchub-stream' ),
					array( 'status' => 403 )
				);
			}

			error_log( '[FCHub Stream] Comment video enabled check passed' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		// Get upload settings (used for both posts and comments).
		$upload_settings = get_option(
			'fchub_stream_upload_settings',
			array(
				'max_file_size_mb' => 500,
				'allowed_formats'  => array( 'mp4', 'mov', 'webm', 'avi' ),
			)
		);

		// Validate file size.
		$max_size_mb  = $upload_settings['max_file_size_mb'] ?? 500;
		$file_size_mb = round( $file['size'] / ( 1024 * 1024 ), 2 );

		if ( $file_size_mb > $max_size_mb ) {
			error_log( '[FCHub Stream] Upload rejected: File too large (' . $file_size_mb . 'MB > ' . $max_size_mb . 'MB)' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %1$s: File size in MB, %2$s: Maximum allowed size in MB */
					__( 'File size (%1$sMB) exceeds maximum allowed size (%2$sMB).', 'fchub-stream' ),
					$file_size_mb,
					$max_size_mb
				),
				array( 'status' => 413 )
			);
		}

		// Validate file format.
		$allowed_formats = $upload_settings['allowed_formats'] ?? array( 'mp4', 'mov', 'webm', 'avi' );
		$file_extension  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( ! in_array( $file_extension, $allowed_formats, true ) ) {
			error_log( '[FCHub Stream] Upload rejected: Invalid format (' . $file_extension . ')' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new WP_Error(
				'invalid_format',
				sprintf(
					/* translators: %1$s: File extension, %2$s: Allowed formats */
					__( 'File format "%1$s" is not allowed. Allowed formats: %2$s', 'fchub-stream' ),
					$file_extension,
					implode( ', ', $allowed_formats )
				),
				array( 'status' => 400 )
			);
		}

		// Get metadata from request.
		$metadata = array();
		$title    = $request->get_param( 'title' );
		if ( ! empty( $title ) ) {
			$metadata['title'] = sanitize_text_field( $title );
		}

		// Add context to metadata for tracking.
		$metadata['context'] = $context;

		// Upload video.
		$result = VideoUploadService::upload(
			$file['tmp_name'],
			sanitize_file_name( $file['name'] ),
			$metadata
		);

		// Clean up temporary file.
		if ( file_exists( $file['tmp_name'] ) ) {
			wp_delete_file( $file['tmp_name'] );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $result,
				'message' => __( 'Video uploaded successfully.', 'fchub-stream' ),
			),
			200
		);
	}

	/**
	 * Check video status
	 *
	 * Handles GET /stream/video-status/{video_id} endpoint.
	 * Checks encoding status of uploaded video.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST API request object.
	 *
	 * @return WP_REST_Response|WP_Error Response with status or error.
	 */
	public function check_status( WP_REST_Request $request ) {
		$video_id = $request->get_param( 'video_id' );
		$provider = $request->get_param( 'provider' );

		SentryService::add_breadcrumb(
			'Status check requested',
			'http',
			'info',
			array(
				'video_id' => $video_id,
				'provider' => $provider,
				'user_id'  => get_current_user_id(),
			)
		);

		// Check permissions - must be logged in user.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'unauthorized',
				__( 'You must be logged in to check video status.', 'fchub-stream' ),
				array( 'status' => 401 )
			);
		}

		// Verify nonce for CSRF protection.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'invalid_nonce',
				__( 'Invalid security token. Please refresh the page and try again.', 'fchub-stream' ),
				array( 'status' => 403 )
			);
		}

		if ( empty( $video_id ) ) {
			return new WP_Error(
				'missing_video_id',
				__( 'Video ID is required.', 'fchub-stream' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $provider ) ) {
			return new WP_Error(
				'missing_provider',
				__( 'Provider is required.', 'fchub-stream' ),
				array( 'status' => 400 )
			);
		}

		$result = VideoUploadService::get_video_status( $video_id, $provider );

		if ( is_wp_error( $result ) ) {
			// If Cloudflare returns 500/404, video might not be ready yet - return pending status instead of error
			// This allows frontend to continue polling.
			$error_code  = $result->get_error_code();
			$error_data  = $result->get_error_data();
			$status_code = $error_data['status'] ?? 0;

			error_log( '[FCHub Stream] Video status check failed - video_id: ' . $video_id . ', provider: ' . $provider . ', error_code: ' . $error_code . ', status_code: ' . $status_code ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			// For 404/500 errors, treat as pending (video may not be ready yet).
			if ( in_array( $status_code, array( 404, 500 ), true ) ) {
				error_log( '[FCHub Stream] Treating error as pending status (video may still be encoding)' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_REST_Response(
					array(
						'success' => true,
						'data'    => array(
							'video_id'        => $video_id,
							'provider'        => $provider,
							'status'          => 'pending',
							'readyToStream'   => false,
							'ready_to_stream' => false,
						),
					),
					200
				);
			}

			return $result;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $result,
			),
			200
		);
	}

	/**
	 * Handle provider webhook
	 *
	 * Handles POST /stream/webhook/{provider} endpoint.
	 * Processes webhook callbacks from Cloudflare Stream or Bunny.net Stream
	 * when video encoding is complete.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST API request object.
	 *
	 * @return WP_REST_Response|WP_Error Response with result or error.
	 */
	public function webhook( WP_REST_Request $request ) {
		$provider = $request->get_param( 'provider' );
		$body     = $request->get_body();

		// Start Sentry transaction for webhook processing.
		$transaction = SentryService::start_transaction(
			'webhook.process',
			'http.server',
			array(
				'provider' => $provider,
				'method'   => $request->get_method(),
			)
		);

		// Set tags for webhook.
		SentryService::set_tags(
			array(
				'webhook_provider' => $provider,
				'webhook_source'   => 'video_encoding',
			)
		);

		SentryService::add_breadcrumb(
			'Webhook received',
			'http',
			'info',
			array(
				'provider'     => $provider,
				'method'       => $request->get_method(),
				'route'        => $request->get_route(),
				'content_type' => $request->get_content_type(),
			)
		);

		error_log( '[FCHub Stream] Webhook endpoint called - Method: ' . $request->get_method() . ', Route: ' . $request->get_route() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		if ( empty( $provider ) ) {
			SentryService::add_breadcrumb(
				'Webhook error: Provider missing',
				'http',
				'error'
			);

			if ( $transaction ) {
				$transaction->setStatus( 'invalid_argument' );
				$transaction->finish();
			}

			error_log( '[FCHub Stream] Webhook error: Provider missing' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new WP_Error(
				'missing_provider',
				__( 'Provider is required.', 'fchub-stream' ),
				array( 'status' => 400 )
			);
		}

		error_log( '[FCHub Stream] Webhook provider: ' . $provider ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		// Verify webhook signature.
		$verification_span = SentryService::start_span( $transaction, 'security', 'Verify webhook signature' );

		$verification = $this->verify_webhook_signature( $request, $provider );

		if ( $verification_span ) {
			$verification_span->finish();
		}

		if ( is_wp_error( $verification ) ) {
			SentryService::add_breadcrumb(
				'Webhook signature verification failed',
				'http',
				'error',
				array( 'error' => $verification->get_error_message() )
			);

			if ( $transaction ) {
				$transaction->setStatus( 'permission_denied' );
				$transaction->finish();
			}

			error_log( '[FCHub Stream] Webhook signature verification failed: ' . $verification->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return $verification;
		}

		SentryService::add_breadcrumb(
			'Webhook signature verified',
			'http',
			'info'
		);

		error_log( '[FCHub Stream] Webhook signature verified successfully' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		// Get webhook data.
		$data = $request->get_json_params();

		if ( empty( $data ) ) {
			SentryService::add_breadcrumb(
				'Webhook error: Empty payload',
				'http',
				'error'
			);

			if ( $transaction ) {
				$transaction->setStatus( 'invalid_argument' );
				$transaction->finish();
			}

			error_log( '[FCHub Stream] Webhook error: Empty payload. Body: ' . $request->get_body() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new WP_Error(
				'invalid_payload',
				__( 'Invalid webhook payload.', 'fchub-stream' ),
				array( 'status' => 400 )
			);
		}

		// Extract video ID from webhook data.
		$video_id = '';
		if ( 'cloudflare_stream' === $provider && isset( $data['uid'] ) ) {
			$video_id = $data['uid'];
		} elseif ( 'bunny_stream' === $provider && isset( $data['VideoGuid'] ) ) {
			$video_id = $data['VideoGuid'];
		}

		SentryService::add_breadcrumb(
			'Webhook payload received',
			'http',
			'info',
			array(
				'video_id' => $video_id,
				'provider' => $provider,
			)
		);

		error_log( '[FCHub Stream] Webhook payload received: ' . wp_json_encode( $data ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		// Process webhook based on provider.
		$processing_span = SentryService::start_span(
			$transaction,
			'db.query',
			'Process webhook and update database',
			array( 'video_id' => $video_id )
		);

		if ( 'cloudflare_stream' === $provider ) {
			$result = $this->process_cloudflare_webhook( $data );
		} elseif ( 'bunny_stream' === $provider ) {
			$result = $this->process_bunny_webhook( $data );
		} else {
			$result = new WP_Error(
				'unsupported_provider',
				__( 'Unsupported provider.', 'fchub-stream' ),
				array( 'status' => 400 )
			);
		}

		if ( $processing_span ) {
			$processing_span->finish();
		}

		if ( is_wp_error( $result ) ) {
			SentryService::add_breadcrumb(
				'Webhook processing failed',
				'http',
				'error',
				array(
					'error'    => $result->get_error_message(),
					'video_id' => $video_id,
				)
			);

			if ( $transaction ) {
				$transaction->setStatus( 'internal_error' );
				$transaction->finish();
			}
		} else {
			SentryService::add_breadcrumb(
				'Webhook processed successfully',
				'http',
				'info',
				array( 'video_id' => $video_id )
			);

			if ( $transaction ) {
				$transaction->setStatus( 'ok' );
				$transaction->finish();
			}
		}

		return $result;
	}

	/**
	 * Verify webhook signature
	 *
	 * Verifies webhook signature from provider to ensure authenticity.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param WP_REST_Request $request  REST API request object.
	 * @param string          $provider Provider name.
	 *
	 * @return true|WP_Error True if valid, WP_Error on failure.
	 */
	private function verify_webhook_signature( WP_REST_Request $request, $provider ) {
		// For Cloudflare Stream.
		if ( 'cloudflare_stream' === $provider ) {
			// Try both uppercase and lowercase header names (WordPress REST API may normalize headers).
			$signature = $request->get_header( 'webhook-signature' );
			if ( ! $signature ) {
				$signature = $request->get_header( 'Webhook-Signature' );
			}

			if ( empty( $signature ) ) {
				error_log( '[FCHub Stream] Webhook signature missing. Headers: ' . wp_json_encode( $request->get_headers() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'missing_signature',
					__( 'Webhook signature is missing.', 'fchub-stream' ),
					array( 'status' => 401 )
				);
			}

			error_log( '[FCHub Stream] Webhook signature found: ' . substr( $signature, 0, 50 ) . '...' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			// Parse signature: time=1230811200,sig1=60493ec9388b44585a29543bcf0de62e....
			// According to Cloudflare docs: https://developers.cloudflare.com/stream/manage-video-library/using-webhooks/.
			$parts = array();
			foreach ( explode( ',', $signature ) as $part ) {
				list( $key, $value ) = explode( '=', $part, 2 );
				$parts[ $key ]       = $value;
			}

			if ( ! isset( $parts['time'] ) || ! isset( $parts['sig1'] ) ) {
				error_log( '[FCHub Stream] Invalid signature format. Parts: ' . wp_json_encode( $parts ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'invalid_signature',
					__( 'Invalid webhook signature format.', 'fchub-stream' ),
					array( 'status' => 401 )
				);
			}

			$timestamp = (int) $parts['time'];
			$sig_hash  = $parts['sig1'];

			// Verify timestamp (prevent replay attacks - allow 5 minutes).
			// Cloudflare docs recommend discarding requests with timestamps that are too old.
			if ( abs( time() - $timestamp ) > 300 ) {
				error_log( '[FCHub Stream] Webhook signature expired. Timestamp: ' . $timestamp . ', Current: ' . time() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'expired_signature',
					__( 'Webhook signature has expired.', 'fchub-stream' ),
					array( 'status' => 401 )
				);
			}

			// Get webhook secret.
			$config = \FCHubStream\App\Services\StreamConfigService::get_private();
			$secret = $config['cloudflare']['webhook_secret'] ?? '';

			if ( empty( $secret ) ) {
				error_log( '[FCHub Stream] Webhook secret not configured in settings' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'missing_secret',
					__( 'Webhook secret not configured.', 'fchub-stream' ),
					array( 'status' => 500 )
				);
			}

			// Verify signature according to Cloudflare docs:
			// 1. Create signature source string: timestamp + '.' + body
			// 2. Compute HMAC-SHA256 using secret and source string
			// 3. Compare signatures using constant-time comparison.
			$body          = $request->get_body();
			$source_string = $timestamp . '.' . $body;
			$expected_sig  = hash_hmac( 'sha256', $source_string, $secret );

			// Use constant-time comparison to prevent timing attacks.
			if ( ! hash_equals( $expected_sig, $sig_hash ) ) {
				error_log( '[FCHub Stream] Signature verification failed. Expected: ' . substr( $expected_sig, 0, 20 ) . '..., Received: ' . substr( $sig_hash, 0, 20 ) . '...' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'invalid_signature',
					__( 'Invalid webhook signature.', 'fchub-stream' ),
					array( 'status' => 401 )
				);
			}

			error_log( '[FCHub Stream] Webhook signature verified successfully' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return true;
		}

		// For Bunny.net Stream - signature verification to be implemented.
		// Bunny.net doesn't use webhook signatures by default.
		// Consider implementing IP whitelist or other security measures.

		return true;
	}

	/**
	 * Process Cloudflare webhook
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param array $data Webhook payload.
	 *
	 * @return WP_REST_Response Response.
	 */
	private function process_cloudflare_webhook( $data ) {
		$video_uid       = $data['uid'] ?? '';
		$ready_to_stream = $data['readyToStream'] ?? false;
		$status_state    = $data['status']['state'] ?? '';
		$err_reason_code = $data['status']['errReasonCode'] ?? $data['status']['errorReasonCode'] ?? '';
		$err_reason_text = $data['status']['errReasonText'] ?? $data['status']['errorReasonText'] ?? '';

		error_log( '[FCHub Stream] Cloudflare webhook received - video_id: ' . $video_uid . ', ready: ' . ( $ready_to_stream ? 'YES' : 'NO' ) . ', state: ' . $status_state ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		if ( empty( $video_uid ) ) {
			return new WP_Error(
				'invalid_payload',
				__( 'Video UID missing in webhook payload.', 'fchub-stream' ),
				array( 'status' => 400 )
			);
		}

		// Handle error state - according to Cloudflare docs, status.state can be 'error'.
		if ( 'error' === $status_state ) {
			error_log( '[FCHub Stream] Video encoding failed - video_id: ' . $video_uid . ', error_code: ' . $err_reason_code . ', error_text: ' . $err_reason_text ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			// Update posts/comments with failed status.
			$this->update_video_status_in_db( $video_uid, 'failed', null, $err_reason_code, $err_reason_text );
			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Webhook processed - video encoding failed.', 'fchub-stream' ),
				),
				200
			);
		}

		// Find posts AND comments with this video_id in meta.
		global $wpdb;
		$posts_table    = $wpdb->prefix . 'fcom_posts';
		$comments_table = $wpdb->prefix . 'fcom_post_comments';

		// Search for video_id in posts meta.
		$posts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT id, meta FROM {$wpdb->prefix}fcom_posts WHERE meta LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'%' . $wpdb->esc_like( $video_uid ) . '%'
			)
		);

		// Search for video_id in comments meta (comments also have meta column).
		$comments = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT id, meta FROM {$wpdb->prefix}fcom_post_comments WHERE meta LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'%' . $wpdb->esc_like( $video_uid ) . '%'
			)
		);

		error_log( '[FCHub Stream] Found ' . count( $posts ) . ' posts and ' . count( $comments ) . ' comments with video_id: ' . $video_uid ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		// Update status and HTML if ready.
		// Important: Only mark as ready if video has playback URLs available.
		// Cloudflare may set readyToStream=true before playback URLs are accessible.
		if ( $ready_to_stream ) {
			// Verify video actually has playback URLs before marking as ready.
			$has_playback = isset( $data['playback']['hls'] ) && ! empty( $data['playback']['hls'] );

			if ( $has_playback ) {
				error_log( '[FCHub Stream] Video has playback URLs - marking as ready' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				$this->update_video_status_in_db( $video_uid, 'ready', $video_uid );
			} else {
				error_log( '[FCHub Stream] Video readyToStream=true but no playback URLs yet - keeping as pending' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				// Don't update status - video not fully ready yet.
			}
		}

		/**
		 * Fires when Cloudflare Stream webhook is received.
		 *
		 * @since 1.0.0
		 *
		 * @param string $video_uid       Video UID.
		 * @param bool   $ready_to_stream Whether video is ready to stream.
		 * @param string $status_state    Current processing state.
		 * @param array  $data            Full webhook payload.
		 */
		do_action( 'fchub_stream_cloudflare_webhook', $video_uid, $ready_to_stream, $status_state, $data );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Webhook processed successfully.', 'fchub-stream' ),
			),
			200
		);
	}

	/**
	 * Process Bunny.net webhook
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param array $data Webhook payload.
	 *
	 * @return WP_REST_Response Response.
	 */
	private function process_bunny_webhook( $data ) {
		$video_guid = $data['guid'] ?? $data['VideoGuid'] ?? '';
		$status     = $data['status'] ?? $data['Status'] ?? 0;

		if ( empty( $video_guid ) ) {
			return new WP_Error(
				'invalid_payload',
				__( 'Video GUID missing in webhook payload.', 'fchub-stream' ),
				array( 'status' => 400 )
			);
		}

		// TODO: Update video status in database or notify user.

		/**
		 * Fires when Bunny.net Stream webhook is received.
		 *
		 * @since 1.0.0
		 *
		 * @param string $video_guid Video GUID.
		 * @param int    $status     Processing status.
		 * @param array  $data       Full webhook payload.
		 */
		do_action( 'fchub_stream_bunny_webhook', $video_guid, $status, $data );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Webhook processed successfully.', 'fchub-stream' ),
			),
			200
		);
	}

	/**
	 * Update video status in database (posts and comments)
	 *
	 * Helper method to update video status in posts and comments meta.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param string      $video_uid      Video UID.
	 * @param string      $status         Status ('ready' or 'failed').
	 * @param string|null $video_id       Video ID for ready status (to generate player HTML).
	 * @param string      $error_code     Error code (for failed status).
	 * @param string      $error_text     Error text (for failed status).
	 *
	 * @return void
	 */
	private function update_video_status_in_db( $video_uid, $status, $video_id = null, $error_code = '', $error_text = '' ) {
		global $wpdb;
		$posts_table    = $wpdb->prefix . 'fcom_posts';
		$comments_table = $wpdb->prefix . 'fcom_post_comments';

		// Find posts and comments with this video_id.
		$posts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT id, meta FROM {$wpdb->prefix}fcom_posts WHERE meta LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'%' . $wpdb->esc_like( $video_uid ) . '%'
			)
		);

		$comments = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT id, meta FROM {$wpdb->prefix}fcom_post_comments WHERE meta LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'%' . $wpdb->esc_like( $video_uid ) . '%'
			)
		);

		// Update posts.
		if ( $posts ) {
			foreach ( $posts as $post ) {
				$meta = maybe_unserialize( $post->meta );

				if ( isset( $meta['media_preview']['video_id'] ) && $meta['media_preview']['video_id'] === $video_uid ) {
					if ( 'ready' === $status && $video_id ) {
						$provider        = $meta['media_preview']['provider'] ?? \FCHubStream\App\Services\StreamConfigService::get_enabled_provider();
						$player_renderer = new \FCHubStream\App\Hooks\PortalIntegration\VideoPlayerRenderer();
						$player_html     = $player_renderer->get_player_html( $video_id, $provider, 'ready' );
						// Don't double-wrap - get_player_html() already returns wrapped HTML.
						$meta['media_preview']['html']   = $player_html;
						$meta['media_preview']['status'] = 'ready';
					} elseif ( 'failed' === $status ) {
						$meta['media_preview']['status']     = 'failed';
						$meta['media_preview']['error_code'] = $error_code;
						$meta['media_preview']['error_text'] = $error_text;
					}

					$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$posts_table,
						array( 'meta' => maybe_serialize( $meta ) ),
						array( 'id' => $post->id ),
						array( '%s' ),
						array( '%d' )
					);
				}
			}
		}

		// Update comments.
		if ( $comments ) {
			foreach ( $comments as $comment_row ) {
				$meta = maybe_unserialize( $comment_row->meta );

				if ( isset( $meta['media_preview']['video_id'] ) && $meta['media_preview']['video_id'] === $video_uid ) {
					if ( 'ready' === $status && $video_id ) {
						$provider        = $meta['media_preview']['provider'] ?? \FCHubStream\App\Services\StreamConfigService::get_enabled_provider();
						$player_renderer = new \FCHubStream\App\Hooks\PortalIntegration\VideoPlayerRenderer();
						$player_html     = $player_renderer->get_player_html( $video_id, $provider, 'ready' );
						// Don't double-wrap - get_player_html() already returns wrapped HTML.
						$meta['media_preview']['html']   = $player_html;
						$meta['media_preview']['status'] = 'ready';
					} elseif ( 'failed' === $status ) {
						$meta['media_preview']['status']     = 'failed';
						$meta['media_preview']['error_code'] = $error_code;
						$meta['media_preview']['error_text'] = $error_text;
					}

					$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$comments_table,
						array( 'meta' => maybe_serialize( $meta ) ),
						array( 'id' => $comment_row->id ),
						array( '%s' ),
						array( '%d' )
					);
				}
			}
		}
	}

	/**
	 * Get upload error message
	 *
	 * Converts PHP upload error codes to user-friendly messages.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param int $error_code PHP upload error code.
	 *
	 * @return string Error message.
	 */
	private function get_upload_error_message( $error_code ) {
		$messages = array(
			UPLOAD_ERR_INI_SIZE   => __( 'File exceeds upload_max_filesize directive in php.ini.', 'fchub-stream' ),
			UPLOAD_ERR_FORM_SIZE  => __( 'File exceeds MAX_FILE_SIZE directive in HTML form.', 'fchub-stream' ),
			UPLOAD_ERR_PARTIAL    => __( 'File was only partially uploaded.', 'fchub-stream' ),
			UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'fchub-stream' ),
			UPLOAD_ERR_NO_TMP_DIR => __( 'Missing temporary folder.', 'fchub-stream' ),
			UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'fchub-stream' ),
			UPLOAD_ERR_EXTENSION  => __( 'A PHP extension stopped the file upload.', 'fchub-stream' ),
		);

		return $messages[ $error_code ] ?? __( 'Unknown upload error.', 'fchub-stream' );
	}
}
