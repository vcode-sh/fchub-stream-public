<?php
/**
 * License API Client
 *
 * Handles HTTP communication with FCHub License API.
 *
 * @package FCHub\License
 * @since 1.0.0
 */

namespace FCHub\License;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class License_API_Client {

	/**
	 * API base URL
	 *
	 * @var string
	 */
	protected $api_base;

	/**
	 * Constructor
	 *
	 * @param string $api_base API base URL
	 */
	public function __construct( string $api_base ) {
		$this->api_base = $api_base;
	}

	/**
	 * Activate license
	 *
	 * @param array $params Activation parameters
	 * @return array|WP_Error Response or error
	 */
	public function activate( array $params ) {
		// oRPC format: /licenses/activate (nested router with slash)
		return $this->request( '/licenses/activate', $params );
	}

	/**
	 * Validate license
	 *
	 * @param array $params Validation parameters
	 * @return array|WP_Error Response or error
	 */
	public function validate( array $params ) {
		// oRPC format: /licenses/validate (nested router with slash)
		return $this->request( '/licenses/validate', $params );
	}

	/**
	 * Deactivate license
	 *
	 * @param array $params Deactivation parameters
	 * @return array|WP_Error Response or error
	 */
	public function deactivate( array $params ) {
		// oRPC format: /licenses/deactivate (nested router with slash)
		return $this->request( '/licenses/deactivate', $params );
	}

	/**
	 * Report tampering event (file modification detected)
	 *
	 * Reports file tampering to FCHub API for security monitoring.
	 *
	 * @param array $params Tampering report parameters:
	 *                      - license_key (string, required)
	 *                      - site_url (string, required)
	 *                      - product (string, optional, default: 'fchub-companion')
	 *                      - file_path (string, required)
	 *                      - expected_hash (string, optional)
	 *                      - actual_hash (string, optional)
	 *                      - detection_method (string, required): 'file_integrity', 'code_modification', or 'manual_check'
	 *                      - metadata (array, optional)
	 * @return array|WP_Error Response or error
	 */
	public function report_tampering( array $params ) {
		return $this->request( '/licenses.reportTampering', $params );
	}

	/**
	 * Report bypass attempt (honeypot function called)
	 *
	 * Reports bypass attempt to FCHub API for security monitoring.
	 * This should be called when a honeypot function is detected.
	 *
	 * @param array $params Bypass attempt parameters:
	 *                      - license_key (string, required)
	 *                      - site_url (string, required)
	 *                      - product (string, optional, default: 'fchub-companion')
	 *                      - function_name (string, required)
	 *                      - call_stack (string, optional)
	 *                      - metadata (array, optional)
	 * @return array|WP_Error Response or error
	 */
	public function report_bypass_attempt( array $params ) {
		return $this->request( '/licenses.reportBypassAttempt', $params );
	}

	/**
	 * Report suspicious activity
	 *
	 * Reports suspicious activity to FCHub API for security monitoring.
	 *
	 * @param array $params Suspicious activity parameters:
	 *                      - license_key (string, required)
	 *                      - site_url (string, required)
	 *                      - product (string, optional, default: 'fchub-companion')
	 *                      - activity_type (string, required): 'license_check_removed', 'license_check_modified', 'validation_disabled', 'storage_tampered', or 'other'
	 *                      - description (string, required)
	 *                      - evidence (array, optional)
	 * @return array|WP_Error Response or error
	 */
	public function report_suspicious_activity( array $params ) {
		return $this->request( '/licenses.reportSuspiciousActivity', $params );
	}

	/**
	 * Make API request (oRPC format)
	 *
	 * oRPC uses procedure name in URL path: POST /rpc/licenses.activate
	 * Body contains input parameters directly (not wrapped in 'json' key)
	 *
	 * @param string $endpoint API endpoint (e.g., '/licenses.activate')
	 * @param array $params Request parameters
	 * @return array|WP_Error Response or error
	 */
	protected function request( string $endpoint, array $params ) {
		// oRPC format: Request body wrapped in 'json' key: {"json": {...}}
		$body = wp_json_encode( array( 'json' => $params ) );
		$url = $this->api_base . $endpoint;
		
		error_log( '[FCHub License SDK] API Request - URL: ' . $url ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[FCHub License SDK] API Request - Body: ' . $body ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		
		$response = wp_remote_post(
			$url,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => $body,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		
		// Log response for debugging
		error_log( '[FCHub License SDK] API Response Status: ' . $status_code ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[FCHub License SDK] API Response Body: ' . substr( $body, 0, 500 ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		
		// Check if response is HTML (404 page or error page)
		if ( $status_code === 404 || ( strpos( $body, '<!DOCTYPE' ) !== false || strpos( $body, '<html' ) !== false ) ) {
			return new \WP_Error(
				'api_endpoint_not_found',
				__( 'License API endpoint not found. Please check API base URL.', 'fchub-license' ),
				array( 
					'status' => $status_code,
					'url' => $this->api_base . $endpoint,
					'body_preview' => substr( $body, 0, 200 ),
				)
			);
		}
		
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error( 
				'json_error', 
				__( 'Invalid JSON response from license API.', 'fchub-license' ),
				array(
					'status' => $status_code,
					'url' => $this->api_base . $endpoint,
					'body_preview' => substr( $body, 0, 200 ),
					'json_error' => json_last_error_msg(),
				)
			);
		}

		// Handle oRPC response format
		// oRPC returns response wrapped in 'json' key: {"json": {...}}
		$response_data = $data['json'] ?? $data;
		
		// Handle oRPC error format
		if ( isset( $response_data['error'] ) ) {
			return new \WP_Error(
				$response_data['error']['code'] ?? 'api_error',
				$response_data['error']['message'] ?? 'API error occurred',
				array( 'status' => $status_code )
			);
		}
		
		// Check for oRPC validation errors (in 'json' wrapper)
		if ( isset( $response_data['code'] ) && $response_data['code'] === 'BAD_REQUEST' ) {
			$error_message = $response_data['message'] ?? 'Input validation failed';
			$issues = $response_data['data']['issues'] ?? array();
			if ( ! empty( $issues ) ) {
				$error_message .= ': ' . wp_json_encode( $issues );
			}
			return new \WP_Error(
				'validation_error',
				$error_message,
				array( 
					'status' => $status_code,
					'issues' => $issues,
				)
			);
		}
		
		// Return unwrapped response
		return $response_data;
	}
}
