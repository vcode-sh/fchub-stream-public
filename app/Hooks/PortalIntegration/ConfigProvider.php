<?php
/**
 * Portal Configuration Provider
 *
 * Provides stream configuration settings to the FluentCommunity portal frontend.
 *
 * @package FCHubStream
 */

namespace FCHubStream\App\Hooks\PortalIntegration;

use FCHubStream\App\Services\StreamConfigService;
use FCHubStream\App\Services\StreamLicenseManager;

/**
 * Class ConfigProvider
 *
 * Handles portal configuration variables for stream settings.
 */
class ConfigProvider {
	/**
	 * Register hooks for portal configuration.
	 *
	 * @return void
	 */
	public function register(): void {
		// Check if hooks are already registered to avoid duplicates.
		// This can happen if register() is called multiple times (e.g., early registration + Application registration).
		if ( ! has_filter( 'fluent_community/portal_vars', array( $this, 'add_portal_vars' ) ) ) {
			add_filter( 'fluent_community/portal_vars', array( $this, 'add_portal_vars' ), 10 );
		}
		if ( ! has_filter( 'fluent_community/general_portal_vars', array( $this, 'add_portal_vars' ) ) ) {
			add_filter( 'fluent_community/general_portal_vars', array( $this, 'add_portal_vars' ), 10 );
		}
	}

	/**
	 * Add portal configuration variables.
	 *
	 * Adds stream settings and upload configuration to portal JavaScript variables.
	 *
	 * @param array $vars Existing portal variables.
	 * @return array Modified portal variables.
	 */
	public function add_portal_vars( array $vars ): array {
		// SECURITY LAYER 1: Check license before exposing any upload functionality to frontend.
		$license_active = false;
		$license_check_error = null;
		
		// Ensure autoloader is loaded (might not be loaded if hook fires early)
		if ( ! class_exists( 'FCHubStream\App\Services\StreamLicenseManager' ) ) {
			// Try to load autoloader if it exists
			$autoload_path = dirname( dirname( dirname( __DIR__ ) ) ) . '/vendor/autoload.php';
			if ( file_exists( $autoload_path ) ) {
				require_once $autoload_path;
			}
		}
		
		// Check if StreamLicenseManager class exists
		if ( ! class_exists( 'FCHubStream\App\Services\StreamLicenseManager' ) ) {
			$license_check_error = 'StreamLicenseManager class not found';
		} else {
			// Check if parent class exists (SDK might not be loaded)
			if ( ! class_exists( 'FCHub\License\License_Manager' ) ) {
				$license_check_error = 'License SDK not loaded';
			} else {
				try {
					$license = new StreamLicenseManager();
					$is_active = $license->is_active();
					$can_upload = $license->can_upload_video();
					$license_active = $is_active && $can_upload;
				} catch ( \Throwable $e ) {
					error_log( '[FCHub Stream] ConfigProvider: License check failed with exception: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					$license_check_error = $e->getMessage();
				}
			}
		}

		// If license is not active, disable all upload functionality.
		if ( ! $license_active ) {
			
			$vars['fchubStreamSettings'] = array(
				'enabled'       => false,
				'provider'      => '',
				'rest_url'      => rest_url( 'fluent-community/v2/stream' ),
				'rest_nonce'    => wp_create_nonce( 'wp_rest' ),
				'upload'        => array(
					'max_file_size'        => 0,
					'allowed_formats'      => array(),
					'allowed_mime_types'   => array(),
					'max_duration_seconds' => 0,
					'polling_interval'     => 30000,
				),
				'comment_video' => array(
					'enabled' => false,
				),
			);
			return $vars;
		}

		$enabled_provider = StreamConfigService::get_enabled_provider();

		// Get upload settings - use same format as backend.
		$upload_settings = get_option( 'fchub_stream_upload_settings', array() );
		// Support both max_file_size and max_file_size_mb for backward compatibility.
		$max_file_size = $upload_settings['max_file_size'] ?? $upload_settings['max_file_size_mb'] ?? 500;

		// Check if upload from portal is enabled.
		$enable_upload_from_portal = $upload_settings['enable_upload_from_portal'] ?? true;

		// Only enable if provider is enabled AND portal upload is enabled AND license is active.
		$is_enabled = ! empty( $enabled_provider ) && $enable_upload_from_portal && $license_active;

		// Get comment video settings.
		$comment_video_settings = StreamConfigService::get_comment_video_settings();

		$vars['fchubStreamSettings'] = array(
			'enabled'       => $is_enabled,
			'provider'      => $enabled_provider,
			'rest_url'      => rest_url( 'fluent-community/v2/stream' ),
			'rest_nonce'    => wp_create_nonce( 'wp_rest' ),
			'upload'        => array(
				'max_file_size'        => $max_file_size, // MB.
				'allowed_formats'      => $upload_settings['allowed_formats'] ?? array( 'mp4', 'mov', 'webm', 'avi' ),
				'allowed_mime_types'   => array( 'video/mp4', 'video/quicktime', 'video/webm', 'video/x-msvideo' ),
				'max_duration_seconds' => $upload_settings['max_duration_seconds'] ?? 0,
				'polling_interval'     => ( $upload_settings['polling_interval'] ?? 30 ) * 1000, // Convert to milliseconds.
			),
			'comment_video' => array(
				'enabled' => $comment_video_settings['enabled'] ?? true,
			),
		);

		return $vars;
	}
}
