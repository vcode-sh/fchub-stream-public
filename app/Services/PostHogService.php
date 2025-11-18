<?php
/**
 * PostHog analytics service.
 *
 * Handles initialization and configuration of PostHog for analytics
 * and user behavior tracking in the FCHub Stream plugin.
 *
 * @package FCHub_Stream
 * @subpackage Services
 * @since 1.1.0
 */

namespace FCHubStream\App\Services;

use function FCHubStream\App\Utils\log_debug;
use function FCHubStream\App\Utils\log_error;

/**
 * PostHog Service class.
 *
 * Provides analytics tracking and user behavior monitoring via PostHog.
 * Handles initialization, user identification, and event capture.
 *
 * @since 1.1.0
 */
class PostHogService {

	/**
	 * Flag indicating if PostHog has been initialized.
	 *
	 * @since 1.1.0
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Check if PostHog class is available.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if PostHog class exists, false otherwise.
	 */
	private static function is_posthog_available(): bool {
		return class_exists( 'PostHog\PostHog' );
	}

	/**
	 * Initialize PostHog with configuration.
	 *
	 * Sets up PostHog analytics with project API key from plugin configuration.
	 * Safe to call multiple times - only initializes once.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if initialized, false if disabled or missing API key.
	 */
	public static function init(): bool {
		// Only initialize once.
		if ( self::$initialized ) {
			return true;
		}

		// Check if PostHog class is available.
		if ( ! self::is_posthog_available() ) {
			return false;
		}

		$config = self::get_config();

		// Check if PostHog is enabled and has API key.
		if ( empty( $config['enabled'] ) || empty( $config['api_key'] ) ) {
			return false;
		}

		try {
			// Double-check that PostHog class exists before using it.
			if ( ! class_exists( 'PostHog\PostHog' ) ) {
				return false;
			}

			\PostHog\PostHog::init(
				$config['api_key'],
				array(
					'host'     => $config['host'] ?? 'https://eu.i.posthog.com',
					// Use lib_curl consumer for async delivery (better performance).
					// Events will be flushed automatically on shutdown via WordPress shutdown hook.
					'consumer' => 'lib_curl',
					// Increase timeout for reliable delivery.
					'timeout'  => 10000, // 10 seconds.
				)
			);

			// Set default user properties if logged in.
			if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
				$current_user = wp_get_current_user();
				self::identify_user(
					$current_user->ID,
					array(
						'username' => $current_user->user_login,
						'email'    => $current_user->user_email,
						'role'     => $current_user->roles[0] ?? 'subscriber',
					)
				);
			}

			// Set default super properties.
			$super_properties = array(
				'plugin'         => 'fchub-stream',
				'plugin_version' => FCHUB_STREAM_VERSION,
				'wp_version'     => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : 'unknown',
				'php_version'    => PHP_VERSION,
				'environment'    => self::get_environment(),
			);

			// Add active provider to super properties (if available).
			// Only try to get provider if WordPress is fully loaded (wp_salt() available).
			try {
				if ( function_exists( 'wp_salt' ) && class_exists( 'FCHubStream\App\Services\StreamConfigService' ) ) {
					$config                              = \FCHubStream\App\Services\StreamConfigService::get_private();
					$active_provider                     = $config['provider'] ?? 'unknown';
					$super_properties['active_provider'] = $active_provider;
				}
			} catch ( \Exception $e ) {  // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Silently continue - don't break init if config fails.
				// This can happen if WordPress is not fully loaded yet (wp_salt() not available).
			}

			self::set_super_properties( $super_properties );

			self::$initialized = true;

			return true;
		} catch ( \Exception $e ) {
			// Silently fail - don't break plugin if PostHog fails.
			log_error( 'Failed to initialize PostHog: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get PostHog configuration.
	 *
	 * Retrieves PostHog settings from plugin configuration.
	 * Uses hardcoded API key from config/app.php (for developer monitoring),
	 * NOT from database (end users don't configure PostHog).
	 *
	 * @since 1.1.0
	 *
	 * @return array {
	 *     PostHog configuration.
	 *
	 *     @type bool   $enabled Whether PostHog is enabled.
	 *     @type string $api_key PostHog project API key.
	 *     @type string $host    PostHog host URL.
	 * }
	 */
	private static function get_config(): array {
		// Read API key from config file (developer configuration, not user-configurable).
		$app_config = include FCHUB_STREAM_DIR . 'config/app.php';
		$api_key    = $app_config['posthog_api_key'] ?? '';
		$host       = $app_config['posthog_host'] ?? 'https://eu.i.posthog.com';

		// PostHog is enabled if API key is set.
		return array(
			'enabled' => ! empty( $api_key ),
			'api_key' => $api_key,
			'host'    => $host,
		);
	}

	/**
	 * Get environment name for PostHog.
	 *
	 * Determines environment based on WordPress configuration.
	 *
	 * @since 1.1.0
	 *
	 * @return string Environment name (production, staging, development).
	 */
	private static function get_environment(): string {
		if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
			return WP_ENVIRONMENT_TYPE;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return 'development';
		}

		return 'production';
	}

	/**
	 * Identify a user.
	 *
	 * Sets user properties for the current user session.
	 *
	 * @since 1.1.0
	 *
	 * @param string|int $distinct_id Distinct ID for the user.
	 * @param array      $properties  User properties to set.
	 *
	 * @return void
	 */
	public static function identify_user( $distinct_id, array $properties = array() ): void {
		if ( ! self::$initialized || ! self::is_posthog_available() ) {
			return;
		}

		try {
			// PostHog PHP SDK expects distinctId and properties at top level.
			$identify_data = array(
				'distinctId' => (string) $distinct_id,
			);

			// Merge properties into identify data.
			if ( ! empty( $properties ) ) {
				$identify_data = array_merge( $identify_data, $properties );
			}

			\PostHog\PostHog::identify( $identify_data );
		} catch ( \Exception $e ) {
			// Silently fail.
			log_error( 'Failed to identify user in PostHog: ' . $e->getMessage() );
		}
	}

	/**
	 * Capture an event.
	 *
	 * Sends an event to PostHog for tracking.
	 *
	 * @since 1.1.0
	 *
	 * @param string     $event      Event name.
	 * @param array      $properties Event properties.
	 * @param string|int $distinct_id Optional distinct ID. Uses current user if not provided.
	 *
	 * @return void
	 */
	public static function capture_event( string $event, array $properties = array(), $distinct_id = null ): void {
		if ( ! self::$initialized || ! self::is_posthog_available() ) {
			return;
		}

		try {
			// Determine distinct ID.
			$distinct_id_value = null;
			if ( $distinct_id ) {
				$distinct_id_value = (string) $distinct_id;
			} elseif ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
				$current_user      = wp_get_current_user();
				$distinct_id_value = (string) $current_user->ID;

				// Identify user if not already done (in case user logged in after init).
				// Only identify once per request to avoid spam.
				if ( ! isset( $GLOBALS['fchub_stream_posthog_identified'] ) ) {
					self::identify_user(
						$current_user->ID,
						array(
							'username' => $current_user->user_login,
							'email'    => $current_user->user_email,
							'role'     => $current_user->roles[0] ?? 'subscriber',
						)
					);
					$GLOBALS['fchub_stream_posthog_identified'] = true;
				}
			} else {
				// Use anonymous ID for non-logged-in users.
				$distinct_id_value = self::get_anonymous_id();
			}

			// Validate distinct ID before sending event.
			// PostHog requires a valid distinctId, otherwise it throws "Already scheduled or no user id".
			if ( empty( $distinct_id_value ) ) {
				log_debug( 'PostHog: Cannot capture event "' . $event . '" - no distinct ID available. Skipping event.' );
				return;
			}

			// Build event data according to PostHog PHP SDK format.
			// Format: PostHog::capture(array('distinctId' => '...', 'event' => '...', 'properties' => array(...))).
			$event_data = array(
				'distinctId' => $distinct_id_value,
				'event'      => $event,
				'properties' => $properties,
			);

			// Debug: Log event being sent (only in debug mode).
			log_debug( sprintf( 'PostHog: Capturing event "%s" for user %s', $event, $distinct_id_value ) );

			\PostHog\PostHog::capture( $event_data );
		} catch ( \Exception $e ) {
			// Silently fail.
			log_error( 'Failed to capture event in PostHog: ' . $e->getMessage() );
		}
	}

	/**
	 * Set super properties.
	 *
	 * Sets properties that will be included with all subsequent events.
	 *
	 * @since 1.1.0
	 *
	 * @param array $properties Super properties to set.
	 *
	 * @return void
	 */
	public static function set_super_properties( array $properties ): void {
		if ( ! self::$initialized || ! self::is_posthog_available() ) {
			return;
		}

		try {
			foreach ( $properties as $key => $value ) {
				\PostHog\PostHog::set( $key, $value );
			}
		} catch ( \Exception $e ) {
			// Silently fail.
			log_error( 'Failed to set super properties in PostHog: ' . $e->getMessage() );
		}
	}

	/**
	 * Get anonymous ID for non-logged-in users.
	 *
	 * Generates or retrieves an anonymous ID for tracking non-authenticated users.
	 *
	 * @since 1.1.0
	 *
	 * @return string Anonymous ID.
	 */
	private static function get_anonymous_id(): string {
		// Check if we have an anonymous ID in session/cookie.
		if ( isset( $_COOKIE['fchub_stream_anon_id'] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE['fchub_stream_anon_id'] ) );
		}

		// Generate new anonymous ID.
		$anon_id = 'anon_' . wp_generate_uuid4();

		// Set cookie for future visits (expires in 1 year).
		if ( ! headers_sent() ) {
			setcookie( 'fchub_stream_anon_id', $anon_id, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
		}

		return $anon_id;
	}

	/**
	 * Track video upload event.
	 *
	 * Specialized method for tracking video upload events.
	 *
	 * @since 1.1.0
	 *
	 * @param array $upload_data Upload data including provider, file size, duration, etc.
	 *
	 * @return void
	 */
	public static function track_video_upload( array $upload_data ): void {
		// Ensure all required properties are non-empty strings.
		$provider = ! empty( $upload_data['provider'] ) ? (string) $upload_data['provider'] : 'unknown';
		$format   = ! empty( $upload_data['format'] ) ? (string) $upload_data['format'] : 'unknown';
		$source   = ! empty( $upload_data['source'] ) ? (string) $upload_data['source'] : 'post';

		$properties = array(
			'provider'                => $provider,
			'file_size_mb'            => $upload_data['file_size_mb'] ?? 0,
			'duration_seconds'        => $upload_data['duration_seconds'] ?? 0,
			'format'                  => $format,
			'source'                  => $source,
			'$process_person_profile' => false, // Don't create person profiles for uploads.
		);

		self::capture_event( 'video_upload', $properties );
	}

	/**
	 * Track video view event.
	 *
	 * Specialized method for tracking video view events.
	 *
	 * @since 1.1.0
	 *
	 * @param string $video_id   Video ID.
	 * @param string $provider   Video provider.
	 * @param string $context    Context (portal, admin, comment).
	 *
	 * @return void
	 */
	public static function track_video_view( string $video_id, string $provider, string $context = 'portal' ): void {
		self::capture_event(
			'video_view',
			array(
				'video_id'                => $video_id,
				'provider'                => $provider,
				'context'                 => $context,
				'$process_person_profile' => false,
			)
		);
	}

	/**
	 * Track plugin installation/activation event.
	 *
	 * Tracks when plugin is activated for the first time or reactivated.
	 *
	 * @since 1.1.0
	 *
	 * @param bool $network_wide Whether activation is network-wide.
	 * @param bool $is_first_activation Whether this is first activation (new installation).
	 *
	 * @return void
	 */
	public static function track_plugin_activation( bool $network_wide = false, bool $is_first_activation = false ): void {
		// Get site info for context.
		$site_url     = function_exists( 'home_url' ) ? home_url() : 'unknown';
		$wp_version   = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : 'unknown';
		$php_version  = PHP_VERSION;
		$is_multisite = function_exists( 'is_multisite' ) && is_multisite();

		self::capture_event(
			'plugin_activated',
			array(
				'network_wide'            => $network_wide,
				'is_first_activation'     => $is_first_activation,
				'is_multisite'            => $is_multisite,
				'plugin_version'          => defined( 'FCHUB_STREAM_VERSION' ) ? FCHUB_STREAM_VERSION : 'unknown',
				'wp_version'              => $wp_version,
				'php_version'             => $php_version,
				'$process_person_profile' => false,
			)
		);
	}

	/**
	 * Track plugin deactivation event.
	 *
	 * Tracks when plugin is deactivated.
	 *
	 * @since 1.1.0
	 *
	 * @param bool $network_wide Whether deactivation is network-wide.
	 *
	 * @return void
	 */
	public static function track_plugin_deactivation( bool $network_wide = false ): void {
		// Get site info for context.
		$site_url     = function_exists( 'home_url' ) ? home_url() : 'unknown';
		$wp_version   = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : 'unknown';
		$php_version  = PHP_VERSION;
		$is_multisite = function_exists( 'is_multisite' ) && is_multisite();

		self::capture_event(
			'plugin_deactivated',
			array(
				'network_wide'            => $network_wide,
				'is_multisite'            => $is_multisite,
				'plugin_version'          => defined( 'FCHUB_STREAM_VERSION' ) ? FCHUB_STREAM_VERSION : 'unknown',
				'wp_version'              => $wp_version,
				'php_version'             => $php_version,
				'$process_person_profile' => false,
			)
		);
	}

	/**
	 * Track video upload failure.
	 *
	 * Tracks when video upload fails with error details.
	 *
	 * @since 1.1.0
	 *
	 * @param string $error_code   Error code.
	 * @param string $error_message Error message.
	 * @param string $provider      Provider name (cloudflare, bunny).
	 * @param array  $context       Additional context (file_size_mb, format, etc.).
	 *
	 * @return void
	 */
	public static function track_video_upload_failed( string $error_code, string $error_message, string $provider = 'unknown', array $context = array() ): void {
		self::capture_event(
			'video_upload_failed',
			array_merge(
				array(
					'error_code'              => $error_code,
					'error_message'           => $error_message,
					'provider'                => $provider,
					'$process_person_profile' => false,
				),
				$context
			)
		);
	}

	/**
	 * Track video validation failure.
	 *
	 * Tracks when video file validation fails.
	 *
	 * @since 1.1.0
	 *
	 * @param string $error_code   Validation error code.
	 * @param string $error_message Error message.
	 * @param array  $context      File context (file_size_mb, format, max_size_mb, etc.).
	 *
	 * @return void
	 */
	public static function track_video_validation_failed( string $error_code, string $error_message, array $context = array() ): void {
		self::capture_event(
			'video_validation_failed',
			array_merge(
				array(
					'error_code'              => $error_code,
					'error_message'           => $error_message,
					'$process_person_profile' => false,
				),
				$context
			)
		);
	}

	/**
	 * Track video upload time.
	 *
	 * Tracks upload performance metrics.
	 *
	 * @since 1.1.0
	 *
	 * @param float  $upload_time_seconds Upload duration in seconds.
	 * @param string $provider            Provider name.
	 * @param array  $context             Additional context (file_size_mb, format, etc.).
	 *
	 * @return void
	 */
	public static function track_upload_time( float $upload_time_seconds, string $provider, array $context = array() ): void {
		// Ensure provider is non-empty string.
		$provider_value = ! empty( $provider ) ? (string) $provider : 'unknown';
		$format_value   = ! empty( $context['format'] ) ? (string) $context['format'] : 'unknown';

		// Ensure upload_time_seconds is a valid positive number (min 0.01 to avoid division issues).
		$upload_time = max( 0.01, (float) $upload_time_seconds );

		// Get upload start time from context if available, otherwise use current time minus upload time.
		$upload_start_time = $context['upload_start_time'] ?? ( time() - (int) $upload_time );

		self::capture_event(
			'video_upload_time',
			array_merge(
				array(
					'upload_time_seconds'     => $upload_time,
					'upload_time_ms'          => round( $upload_time * 1000, 2 ),
					'upload_start_time'       => $upload_start_time, // Timestamp when upload started.
					'provider'                => $provider_value,
					'format'                  => $format_value, // Ensure format is included.
					'$process_person_profile' => false,
				),
				$context
			)
		);
	}

	/**
	 * Track video encoding time.
	 *
	 * Tracks encoding performance metrics (time from upload to ready).
	 *
	 * @since 1.1.0
	 *
	 * @param float  $encoding_time_seconds Encoding duration in seconds.
	 * @param string $provider             Provider name.
	 * @param array  $context              Additional context (file_size_mb, format, etc.).
	 *
	 * @return void
	 */
	public static function track_encoding_time( float $encoding_time_seconds, string $provider, array $context = array() ): void {
		// Ensure provider is non-empty string.
		$provider_value = ! empty( $provider ) ? (string) $provider : 'unknown';
		$format_value   = ! empty( $context['format'] ) ? (string) $context['format'] : 'unknown';

		// Ensure encoding_time_seconds is a valid positive number (min 0.01 to avoid division issues).
		$encoding_time = max( 0.01, (float) $encoding_time_seconds );

		// Get upload start time from context (should be set from transient during webhook processing).
		$upload_start_time = $context['upload_start_time'] ?? null;

		// Get upload time from context if available (for calculating total time to ready).
		$upload_time_seconds = $context['upload_time_seconds'] ?? 0;
		$upload_time         = max( 0, (float) $upload_time_seconds );

		// Calculate total time from upload start to ready (upload + encoding).
		$total_time_to_ready = $upload_time + $encoding_time;

		$properties = array_merge(
			array(
				'encoding_time_seconds'       => $encoding_time,
				'encoding_time_minutes'       => round( $encoding_time / 60, 2 ),
				'upload_time_seconds'         => $upload_time, // Include upload time if available.
				'total_time_to_ready'         => $total_time_to_ready, // Total time: upload + encoding.
				'total_time_to_ready_minutes' => round( $total_time_to_ready / 60, 2 ),
				'provider'                    => $provider_value,
				'format'                      => $format_value, // Ensure format is included.
				'$process_person_profile'     => false,
			),
			$context
		);

		// Add upload start time if available.
		if ( $upload_start_time ) {
			$properties['upload_start_time'] = $upload_start_time;
		}

		self::capture_event( 'video_encoding_time', $properties );
	}

	/**
	 * Track upload cancellation.
	 *
	 * Tracks when user cancels an upload.
	 *
	 * @since 1.1.0
	 *
	 * @param string $provider Provider name.
	 * @param array  $context  Additional context (file_size_mb, format, upload_progress, etc.).
	 *
	 * @return void
	 */
	public static function track_upload_cancelled( string $provider = 'unknown', array $context = array() ): void {
		self::capture_event(
			'upload_cancelled',
			array_merge(
				array(
					'provider'                => $provider,
					'$process_person_profile' => false,
				),
				$context
			)
		);
	}

	/**
	 * Track upload retry.
	 *
	 * Tracks when user retries an upload after failure.
	 *
	 * @since 1.1.0
	 *
	 * @param string $error_code   Previous error code.
	 * @param string $provider      Provider name.
	 * @param int    $retry_attempt Retry attempt number.
	 * @param array  $context       Additional context.
	 *
	 * @return void
	 */
	public static function track_upload_retry( string $error_code, string $provider = 'unknown', int $retry_attempt = 1, array $context = array() ): void {
		self::capture_event(
			'upload_retry',
			array_merge(
				array(
					'error_code'              => $error_code,
					'provider'                => $provider,
					'retry_attempt'           => $retry_attempt,
					'$process_person_profile' => false,
				),
				$context
			)
		);
	}

	/**
	 * Track video encoding failure.
	 *
	 * Tracks when video encoding fails.
	 *
	 * @since 1.1.0
	 *
	 * @param string $video_id     Video ID.
	 * @param string $provider     Provider name.
	 * @param string $error_code   Error code.
	 * @param string $error_message Error message.
	 * @param array  $context      Additional context.
	 *
	 * @return void
	 */
	public static function track_encoding_failed( string $video_id, string $provider, string $error_code, string $error_message, array $context = array() ): void {
		self::capture_event(
			'video_encoding_failed',
			array_merge(
				array(
					'video_id'                => $video_id,
					'provider'                => $provider,
					'error_code'              => $error_code,
					'error_message'           => $error_message,
					'$process_person_profile' => false,
				),
				$context
			)
		);
	}

	/**
	 * Track video status check event.
	 *
	 * Tracks when user checks video encoding status.
	 *
	 * @since 1.1.0
	 *
	 * @param string $video_id   Video ID.
	 * @param string $provider   Provider name.
	 * @param string $status     Current status (pending, ready, error).
	 * @param array  $context    Additional context (check_count, etc.).
	 *
	 * @return void
	 */
	public static function track_status_check( string $video_id, string $provider, string $status, array $context = array() ): void {
		// Normalize provider (add _stream suffix if not present for consistency).
		if ( ! str_ends_with( $provider, '_stream' ) ) {
			$provider = $provider . '_stream';
		}

		self::capture_event(
			'video_status_check',
			array_merge(
				array(
					'video_id'                => $video_id,
					'provider'                => $provider,
					'status'                  => $status,
					'$process_person_profile' => false,
				),
				$context
			)
		);
	}

	/**
	 * Track provider switch event.
	 *
	 * Tracks when active provider is changed.
	 *
	 * @since 1.1.0
	 *
	 * @param string $old_provider Previous provider name.
	 * @param string $new_provider New provider name.
	 *
	 * @return void
	 */
	public static function track_provider_switched( string $old_provider, string $new_provider ): void {
		// Normalize providers (add _stream suffix if not present for consistency).
		if ( ! str_ends_with( $old_provider, '_stream' ) ) {
			$old_provider = $old_provider . '_stream';
		}
		if ( ! str_ends_with( $new_provider, '_stream' ) ) {
			$new_provider = $new_provider . '_stream';
		}

		self::capture_event(
			'provider_switched',
			array(
				'old_provider'            => $old_provider,
				'new_provider'            => $new_provider,
				'$process_person_profile' => false,
			)
		);
	}

	/**
	 * Track provider configuration event.
	 *
	 * Tracks when a provider is configured or reconfigured.
	 *
	 * @since 1.1.0
	 *
	 * @param string $provider Provider name (cloudflare, bunny).
	 * @param bool   $success  Whether configuration was successful.
	 * @param string $error    Error message if configuration failed.
	 *
	 * @return void
	 */
	public static function track_provider_config( string $provider, bool $success, string $error = '' ): void {
		// Normalize provider (add _stream suffix if not present for consistency).
		if ( ! str_ends_with( $provider, '_stream' ) ) {
			$provider = $provider . '_stream';
		}

		self::capture_event(
			'provider_config',
			array(
				'provider'                => $provider,
				'success'                 => $success ? 'true' : 'false', // Convert boolean to string for consistent breakdowns.
				'error'                   => $error,
				'$process_person_profile' => false, // Don't create person profiles for config events.
			)
		);
	}

	/**
	 * Check if PostHog is initialized.
	 *
	 * Returns whether PostHog analytics is currently active.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if initialized, false otherwise.
	 */
	public static function is_initialized(): bool {
		return self::$initialized;
	}

	/**
	 * Test PostHog connection.
	 *
	 * Sends a test event to verify PostHog is working correctly.
	 *
	 * @since 1.1.0
	 *
	 * @return array {
	 *     Test result.
	 *
	 *     @type string $status  Status (success or error).
	 *     @type string $message Status message.
	 * }
	 */
	public static function test_connection(): array {
		$config = self::get_config();

		if ( empty( $config['api_key'] ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'PostHog API key is not configured.', 'fchub-stream' ),
			);
		}

		// Try to initialize if not already done.
		if ( ! self::$initialized ) {
			$initialized = self::init();
			if ( ! $initialized ) {
				return array(
					'status'  => 'error',
					'message' => __( 'Failed to initialize PostHog. Check API key format.', 'fchub-stream' ),
				);
			}
		}

		try {
			// Send a test event.
			self::capture_event(
				'posthog_test',
				array(
					'test_type' => 'connection_test',
					'timestamp' => time(),
				)
			);

			// Flush to ensure event is sent immediately.
			self::flush();

			return array(
				'status'  => 'success',
				'message' => __( 'PostHog connection test successful. Check your PostHog dashboard for the test event.', 'fchub-stream' ),
			);
		} catch ( \Exception $e ) {
			return array(
				'status'  => 'error',
				'message' => sprintf(
					/* translators: %s: Error message */
					__( 'PostHog connection test failed: %s', 'fchub-stream' ),
					$e->getMessage()
				),
			);
		}
	}

	/**
	 * Flush pending events.
	 *
	 * Forces PostHog to send any pending events immediately.
	 * Useful for ensuring events are sent before script termination.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function flush(): void {
		if ( ! self::$initialized || ! self::is_posthog_available() ) {
			return;
		}

		try {
			// Debug: Log flush attempt (only in debug mode).
			log_debug( 'PostHog: Flushing events...' );

			\PostHog\PostHog::flush();
		} catch ( \Exception $e ) {
			// Silently fail.
			log_error( 'Failed to flush PostHog events: ' . $e->getMessage() );
		}
	}
}
