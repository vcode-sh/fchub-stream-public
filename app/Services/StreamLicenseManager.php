<?php
/**
 * Stream License Manager
 *
 * Extends FCHub core license manager for Stream plugin.
 * Handles license activation, validation, and feature checking for FCHub Stream.
 *
 * @package FCHubStream
 * @subpackage Services
 * @since 1.0.0
 */

namespace FCHubStream\App\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Use fully qualified class name to avoid issues if SDK is not loaded yet
// The class will be checked before instantiation in controllers

/**
 * Stream License Manager class.
 *
 * Provides license management specifically for FCHub Stream plugin.
 * Extends the universal License_Manager to identify this plugin as 'fchub-stream'.
 *
 * @since 1.0.0
 */
class StreamLicenseManager extends \FCHub\License\License_Manager {

	/**
	 * Get product slug
	 *
	 * Identifies this plugin as 'fchub-stream' to the FCHub licensing API.
	 *
	 * @since 1.0.0
	 *
	 * @return string Product slug.
	 */
	protected function get_product_slug(): string {
		return 'fchub-stream';
	}

	/**
	 * Check if feature is enabled
	 *
	 * Checks if a specific feature is enabled in the license.
	 * Handles both Companion format (direct fields) and Stream format (features object).
	 *
	 * @since 1.0.0
	 *
	 * @param string $feature Feature name to check.
	 *
	 * @return bool True if feature is enabled, false otherwise.
	 */
	public function is_feature_enabled( string $feature ): bool {
		$features = $this->get_features();
		
		// Check if feature exists in features array
		// Note: License can have both max_sites (Companion format) AND Stream features (video_upload, etc.)
		// So we check for the specific feature regardless of max_sites presence
		if ( isset( $features[ $feature ] ) ) {
			// Feature exists - return its value (true/false)
			return (bool) $features[ $feature ];
		}

		// Feature not found
		return false;
	}

	/**
	 * Get stored license data
	 *
	 * Returns raw license data from storage (for tamper detection).
	 *
	 * @since 1.0.0
	 *
	 * @return array|null License data or null
	 */
	public function get_stored_data(): ?array {
		return $this->storage->get();
	}

	/**
	 * Check if video upload is allowed
	 *
	 * Checks if the license allows video uploads.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if video upload is allowed, false otherwise.
	 */
	public function can_upload_video(): bool {
		// Honeypot check - if someone modifies this to bypass, they'll call honeypot
		if ( ! $this->is_active() ) {
			// Honeypot: This function should NEVER be called if license is inactive
			if ( class_exists( 'FCHubStream\App\Services\TamperDetection' ) ) {
				TamperDetection::_internal_bypass_check();
			}
			return false;
		}

		return $this->is_feature_enabled( 'video_upload' );
	}

	/**
	 * Get maximum video size in GB
	 *
	 * Returns the maximum video size allowed by the license.
	 *
	 * @since 1.0.0
	 *
	 * @return int Maximum video size in GB. Returns 0 if not set (unlimited).
	 */
	public function get_max_video_size_gb(): int {
		$features = $this->get_features();
		return intval( $features['max_video_size_gb'] ?? 0 );
	}

	/**
	 * Check if Cloudflare Stream is enabled
	 *
	 * Checks if the license allows using Cloudflare Stream.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if Cloudflare Stream is enabled, false otherwise.
	 */
	public function has_cloudflare_stream(): bool {
		return $this->is_active() && $this->is_feature_enabled( 'cloudflare_stream' );
	}

	/**
	 * Check if analytics is enabled
	 *
	 * Checks if the license includes analytics features.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if analytics is enabled, false otherwise.
	 */
	public function has_analytics(): bool {
		return $this->is_active() && $this->is_feature_enabled( 'analytics' );
	}
}
