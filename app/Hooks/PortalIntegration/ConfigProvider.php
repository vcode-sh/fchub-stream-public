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
		add_filter( 'fluent_community/portal_vars', array( $this, 'add_portal_vars' ), 10 );
		add_filter( 'fluent_community/general_portal_vars', array( $this, 'add_portal_vars' ), 10 );
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
		$enabled_provider = StreamConfigService::get_enabled_provider();

		// Get upload settings - use same format as backend.
		$upload_settings = get_option( 'fchub_stream_upload_settings', array() );
		// Support both max_file_size and max_file_size_mb for backward compatibility.
		$max_file_size = $upload_settings['max_file_size'] ?? $upload_settings['max_file_size_mb'] ?? 500;

		// Check if upload from portal is enabled.
		$enable_upload_from_portal = $upload_settings['enable_upload_from_portal'] ?? true;

		// Only enable if provider is enabled AND portal upload is enabled.
		$is_enabled = ! empty( $enabled_provider ) && $enable_upload_from_portal;

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
