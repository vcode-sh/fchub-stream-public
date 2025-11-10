<?php
/**
 * Tamper Detection Service
 *
 * Detects file modifications, bypass attempts, and suspicious activity.
 * Reports security events to FCHub API for real-time monitoring.
 *
 * @package FCHubStream
 * @subpackage Services
 * @since 1.0.0
 */

namespace FCHubStream\App\Services;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tamper Detection class.
 *
 * Provides multiple layers of security detection:
 * - File integrity checks
 * - Honeypot function monitoring
 * - Suspicious activity detection
 *
 * @since 1.0.0
 */
class TamperDetection {

	/**
	 * API base URL
	 *
	 * @var string
	 */
	private const API_BASE = 'https://api.fchub.co/rpc';

	/**
	 * Critical files to monitor for tampering
	 *
	 * @var array
	 */
	private const CRITICAL_FILES = array(
		'app/Services/StreamLicenseManager.php',
		'app/Services/VideoUploadService.php',
		'app/Http/Controllers/VideoUploadController.php',
	);

	/**
	 * Expected file hashes (MD5) - populated on first check
	 *
	 * @var array
	 */
	private static $file_hashes = array();

	/**
	 * Initialize tamper detection
	 *
	 * Sets up file integrity monitoring and honeypot functions.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function init(): void {
		// Initialize file hashes on first run
		self::initialize_file_hashes();
	}

	/**
	 * Initialize expected file hashes
	 *
	 * Calculates and stores MD5 hashes of critical files.
	 * These hashes are used to detect modifications.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function initialize_file_hashes(): void {
		if ( ! empty( self::$file_hashes ) ) {
			return; // Already initialized
		}

		$plugin_dir = dirname( dirname( dirname( __DIR__ ) ) );

		foreach ( self::CRITICAL_FILES as $file ) {
			$file_path = $plugin_dir . '/' . $file;
			if ( file_exists( $file_path ) ) {
				self::$file_hashes[ $file ] = md5_file( $file_path );
			}
		}

		// Store hashes in transient (24h expiry)
		set_transient( 'fchub_stream_file_hashes', self::$file_hashes, DAY_IN_SECONDS );
	}

	/**
	 * Check file integrity
	 *
	 * Compares current file hashes with expected hashes.
	 * Reports tampering if mismatch detected.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context Context of the check (e.g., 'upload', 'validation').
	 * @return bool True if integrity check passed, false otherwise.
	 */
	public static function check_file_integrity( string $context = 'general' ): bool {
		// Load stored hashes
		$stored_hashes = get_transient( 'fchub_stream_file_hashes' );
		if ( empty( $stored_hashes ) ) {
			self::initialize_file_hashes();
			$stored_hashes = self::$file_hashes;
		}

		$plugin_dir = dirname( dirname( dirname( __DIR__ ) ) );
		$license_manager = new StreamLicenseManager();
		$stored_data = $license_manager->get_stored_data();
		$license_key = $stored_data['key'] ?? null;

		if ( ! $license_key ) {
			return true; // No license = no tampering check needed
		}

		$tampered_files = array();

		foreach ( self::CRITICAL_FILES as $file ) {
			$file_path = $plugin_dir . '/' . $file;
			if ( ! file_exists( $file_path ) ) {
				continue; // File doesn't exist, skip
			}

			$current_hash = md5_file( $file_path );
			$expected_hash = $stored_hashes[ $file ] ?? null;

			if ( $expected_hash && $current_hash !== $expected_hash ) {
				$tampered_files[] = $file;

				// Report tampering immediately
				self::report_tampering(
					$license_key,
					$file,
					$expected_hash,
					$current_hash,
					'file_integrity',
					array(
						'context' => $context,
						'plugin_version' => FCHUB_STREAM_VERSION ?? 'unknown',
					)
				);
			}
		}

		return empty( $tampered_files );
	}

	/**
	 * Report tampering event to FCHub API
	 *
	 * @since 1.0.0
	 *
	 * @param string $license_key License key.
	 * @param string $file_path File path that was tampered with.
	 * @param string|null $expected_hash Expected file hash.
	 * @param string|null $actual_hash Actual file hash.
	 * @param string $detection_method Detection method ('file_integrity', 'code_modification', 'manual_check').
	 * @param array  $metadata Additional metadata.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function report_tampering(
		string $license_key,
		string $file_path,
		?string $expected_hash = null,
		?string $actual_hash = null,
		string $detection_method = 'file_integrity',
		array $metadata = array()
	) {
		$site_url = get_site_url();

		$response = wp_remote_post(
			self::API_BASE . '/licenses.reportTampering',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'license_key'     => $license_key,
						'site_url'        => $site_url,
						'product'         => 'fchub-stream',
						'file_path'       => $file_path,
						'expected_hash'   => $expected_hash,
						'actual_hash'     => $actual_hash,
						'detection_method' => $detection_method,
						'metadata'        => $metadata,
					)
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[FCHub Stream] Failed to report tampering: ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['error'] ) ) {
			error_log( '[FCHub Stream] Tampering report error: ' . wp_json_encode( $data['error'] ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new WP_Error( 'tampering_report_failed', $data['error']['message'] ?? 'Unknown error' );
		}

		return true;
	}

	/**
	 * Report bypass attempt (honeypot function called)
	 *
	 * This function should NEVER be called in normal operation.
	 * If called, it indicates a bypass attempt.
	 *
	 * @since 1.0.0
	 *
	 * @param string $function_name Name of the honeypot function that was called.
	 * @param array  $metadata Additional metadata (e.g., call stack).
	 * @return void
	 */
	public static function report_bypass_attempt( string $function_name, array $metadata = array() ): void {
		$license_manager = new StreamLicenseManager();
		$stored_data = $license_manager->get_stored_data();
		$license_key = $stored_data['key'] ?? null;

		if ( ! $license_key ) {
			return; // No license = no reporting
		}

		$site_url = get_site_url();

		// Capture call stack (limited to 10 frames)
		$call_stack = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$call_stack_str = wp_json_encode( array_slice( $call_stack, 1, 5 ) ); // Skip this function

		wp_remote_post(
			self::API_BASE . '/licenses.reportBypassAttempt',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'license_key'  => $license_key,
						'site_url'    => $site_url,
						'product'     => 'fchub-stream',
						'function_name' => $function_name,
						'call_stack'  => $call_stack_str,
						'metadata'    => $metadata,
					)
				),
				'timeout' => 10,
			)
		);

		// Log locally as well
		error_log( '[FCHub Stream] BYPASS ATTEMPT DETECTED: ' . $function_name ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Report suspicious activity
	 *
	 * @since 1.0.0
	 *
	 * @param string $activity_type Type of suspicious activity.
	 * @param string $description Description of the activity.
	 * @param array  $evidence Additional evidence.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function report_suspicious_activity(
		string $activity_type,
		string $description,
		array $evidence = array()
	) {
		$license_manager = new StreamLicenseManager();
		$stored_data = $license_manager->get_stored_data();
		$license_key = $stored_data['key'] ?? null;

		if ( ! $license_key ) {
			return true; // No license = no reporting
		}

		$site_url = get_site_url();

		$response = wp_remote_post(
			self::API_BASE . '/licenses.reportSuspiciousActivity',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'license_key'   => $license_key,
						'site_url'     => $site_url,
						'product'      => 'fchub-stream',
						'activity_type' => $activity_type,
						'description'  => $description,
						'evidence'     => $evidence,
					)
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[FCHub Stream] Failed to report suspicious activity: ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return $response;
		}

		return true;
	}

	/**
	 * Honeypot function - should NEVER be called
	 *
	 * This function is intentionally hidden and should never be called in normal operation.
	 * If called, it indicates a bypass attempt.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Always returns false (but reports bypass attempt first).
	 */
	public static function _internal_bypass_check(): bool {
		self::report_bypass_attempt( '_internal_bypass_check', array( 'honeypot' => true ) );
		return false;
	}
}

