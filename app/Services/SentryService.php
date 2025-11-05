<?php
/**
 * Sentry error monitoring service.
 *
 * Handles initialization and configuration of Sentry for error tracking
 * and monitoring in the FCHub Stream plugin.
 *
 * @package FCHub_Stream
 * @subpackage Services
 * @since 1.0.0
 */

namespace FCHubStream\App\Services;

use FCHubStream\App\Models\StreamConfig;
use Sentry\State\Scope;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\TransactionContext;
use Sentry\Tracing\SpanStatus;
use Sentry\SentrySdk;
use Sentry\Severity;
use function Sentry\init;
use function Sentry\captureException;
use function Sentry\captureMessage;
use function Sentry\addBreadcrumb;
use function Sentry\startTransaction;

/**
 * Sentry Service class.
 *
 * Provides error monitoring and tracking via Sentry.
 * Handles initialization, context enrichment, and error capture.
 *
 * @since 1.0.0
 */
class SentryService {

	/**
	 * Flag indicating if Sentry has been initialized.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Initialize Sentry with configuration.
	 *
	 * Sets up Sentry error monitoring with DSN from plugin configuration.
	 * Safe to call multiple times - only initializes once.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if initialized, false if disabled or missing DSN.
	 */
	public static function init(): bool {
		// Only initialize once.
		if ( self::$initialized ) {
			return true;
		}

		$config = self::get_config();

		// Check if Sentry is enabled and has DSN.
		if ( empty( $config['enabled'] ) || empty( $config['dsn'] ) ) {
			return false;
		}

		try {
			// Get sample rate - environment-based if not configured.
			$traces_sample_rate = $config['traces_sample_rate'] ?? self::get_default_sample_rate();

			init(
				array(
					'dsn'                   => $config['dsn'],
					'environment'           => self::get_environment(),
					'release'               => self::get_release(),
					'traces_sample_rate'    => (float) $traces_sample_rate,
					'enable_logs'           => true,
					'before_send'           => array( __CLASS__, 'before_send' ),
					'before_breadcrumb'     => array( __CLASS__, 'before_breadcrumb' ),
					'max_request_body_size' => 'small', // Only capture small request bodies (4KB) for security.
					'context_lines'         => 3, // Reduce context lines from default 5 to save space.
					'max_value_length'      => 1024, // Truncate long values (default, but explicit).
					'in_app_include'        => array(
						// Mark plugin files as "in app" for better stack trace grouping.
						'FCHubStream',
					),
					'in_app_exclude'        => array(
						// Exclude vendor and WordPress core from "in app" stack traces.
						'vendor',
						'wp-content',
						'wp-includes',
						'wp-admin',
					),
					// Note: ignore_exceptions only works for exception classes.
					// WP_Error is not an exception, so it won't be caught here.
					// If needed, filter WP_Error in before_send callback instead.
					'send_default_pii'      => false, // Explicitly disable PII (default, but explicit for clarity).
				)
			);

			// Set default context.
			\Sentry\configureScope(
				function ( Scope $scope ) use ( $config ): void {
					// Add user context if logged in (only if WordPress functions are available).
					if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
						$current_user = wp_get_current_user();
						$scope->setUser(
							array(
								'id'       => $current_user->ID,
								'username' => $current_user->user_login,
								// Email excluded for GDPR compliance - add if needed.
								// 'email' => $current_user->user_email,.
							)
						);
					}

					// Add plugin tags.
					$scope->setTag( 'plugin', 'fchub-stream' );
					$scope->setTag( 'plugin_version', FCHUB_STREAM_VERSION );

					// Only add WP version if WordPress is loaded.
					if ( function_exists( 'get_bloginfo' ) ) {
						$scope->setTag( 'wp_version', get_bloginfo( 'version' ) );
					}

					$scope->setTag( 'php_version', PHP_VERSION );

					// Add provider info if available.
					$provider = $config['active_provider'] ?? 'unknown';
					$scope->setTag( 'stream_provider', $provider );
				}
			);

			self::$initialized = true;

			return true;
		} catch ( \Exception $e ) {
			// Silently fail - don't break plugin if Sentry fails.
			error_log( '[FCHub Stream] Failed to initialize Sentry: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get Sentry configuration.
	 *
	 * Retrieves Sentry settings from plugin configuration.
	 * Uses hardcoded DSN from config/app.php (for developer monitoring),
	 * NOT from database (end users don't configure Sentry).
	 *
	 * @since 1.0.0
	 *
	 * @return array {
	 *     Sentry configuration.
	 *
	 *     @type bool   $enabled         Whether Sentry is enabled.
	 *     @type string $dsn             Sentry DSN.
	 *     @type string $active_provider Active stream provider.
	 * }
	 */
	private static function get_config(): array {
		// Read DSN from config file (developer configuration, not user-configurable).
		$app_config = include FCHUB_STREAM_DIR . 'config/app.php';
		$dsn        = $app_config['sentry_dsn'] ?? '';

		// Sentry is enabled if DSN is set.
		$sentry_config = array(
			'enabled' => ! empty( $dsn ),
			'dsn'     => $dsn,
		);

		// Get active provider for context.
		$config   = StreamConfig::get();
		$provider = $config['provider'] ?? 'unknown';
		if ( 'cloudflare' === $provider ) {
			$active_provider = ! empty( $config['cloudflare']['enabled'] ) ? 'cloudflare_stream' : 'none';
		} elseif ( 'bunny' === $provider ) {
			$active_provider = ! empty( $config['bunny']['enabled'] ) ? 'bunny_stream' : 'none';
		} else {
			$active_provider = 'none';
		}

		$sentry_config['active_provider'] = $active_provider;

		// Get traces_sample_rate from hardcoded config first (developer-only configuration).
		// If not set in config/app.php, use environment-based default.
		if ( isset( $app_config['sentry_traces_sample_rate'] ) ) {
			$hardcoded_rate = $app_config['sentry_traces_sample_rate'];
			// Allow null to use environment-based default.
			if ( null !== $hardcoded_rate ) {
				$sentry_config['traces_sample_rate'] = (float) $hardcoded_rate;
			}
		}

		return $sentry_config;
	}

	/**
	 * Get environment name for Sentry.
	 *
	 * Determines environment based on WordPress configuration.
	 *
	 * @since 1.0.0
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
	 * Get default sample rate based on environment.
	 *
	 * Returns environment-appropriate sampling rate:
	 * - development: 1.0 (100% - capture everything)
	 * - staging/beta: 0.7 (70% - good visibility for beta testing)
	 * - production: 0.1 (10% - cost-effective)
	 *
	 * @since 1.0.0
	 *
	 * @return float Sample rate between 0.0 and 1.0.
	 */
	private static function get_default_sample_rate(): float {
		$environment = self::get_environment();

		switch ( $environment ) {
			case 'development':
			case 'local':
				return 1.0; // 100% for dev.

			case 'staging':
			case 'beta':
				return 0.7; // 70% for beta testing (good visibility).

			case 'production':
			default:
				return 0.1; // 10% for production.
		}
	}

	/**
	 * Get release version for Sentry.
	 *
	 * Returns plugin version for release tracking.
	 *
	 * @since 1.0.0
	 *
	 * @return string Release version string.
	 */
	private static function get_release(): string {
		return 'fchub-stream@' . FCHUB_STREAM_VERSION;
	}

	/**
	 * Before send callback for Sentry events.
	 *
	 * Filters and scrubs sensitive data before sending to Sentry.
	 * Removes API keys, tokens, passwords, DSN, and other credentials.
	 * Also scrubs stack locals (local variables in stack trace) and HTTP context.
	 *
	 * @since 1.0.0
	 *
	 * @param \Sentry\Event          $event Event to be sent.
	 * @param \Sentry\EventHint|null $hint Optional event hint.
	 *
	 * @return \Sentry\Event|null Event to send, or null to discard.
	 */
	public static function before_send( \Sentry\Event $event, ?\Sentry\EventHint $hint = null ): ?\Sentry\Event {
		// Scrub sensitive data from request data.
		$request = $event->getRequest();
		if ( $request ) {
			// Scrub from POST data.
			$data = $request['data'] ?? array();
			if ( is_array( $data ) ) {
				$data            = self::scrub_sensitive_data( $data );
				$request['data'] = $data;
			}

			// Scrub from query string (GET parameters).
			if ( isset( $request['query_string'] ) && is_string( $request['query_string'] ) ) {
				parse_str( $request['query_string'], $query_params );
				if ( is_array( $query_params ) ) {
					$query_params            = self::scrub_sensitive_data( $query_params );
					$request['query_string'] = http_build_query( $query_params );
				}
			}

			// Scrub from headers (especially Authorization, X-API-Key, etc.).
			if ( isset( $request['headers'] ) && is_array( $request['headers'] ) ) {
				$request['headers'] = self::scrub_sensitive_data( $request['headers'] );
			}

			$event->setRequest( $request );
		}

		// Scrub from extra context.
		$extra = $event->getExtra();
		if ( is_array( $extra ) ) {
			$extra = self::scrub_sensitive_data( $extra );
			$event->setExtra( $extra );
		}

		// Note: Breadcrumbs are already scrubbed in before_breadcrumb callback.
		// Sentry Event doesn't have setBreadcrumbs() method anyway.

		// Scrub from stack trace locals (local variables in stack frames).
		// PHP SDK captures local variables when exceptions occur - these may contain sensitive data.
		$exceptions = $event->getExceptions();
		if ( ! empty( $exceptions ) ) {
			foreach ( $exceptions as $exception ) {
				$stacktrace = $exception->getStacktrace();
				if ( $stacktrace ) {
					$frames = $stacktrace->getFrames();
					if ( ! empty( $frames ) ) {
						foreach ( $frames as $frame ) {
							// Scrub local variables in each stack frame.
							$vars = $frame->getVars();
							if ( is_array( $vars ) && ! empty( $vars ) ) {
								$frame->setVars( self::scrub_sensitive_data( $vars ) );
							}
						}
					}
				}
			}
		}

		return $event;
	}

	/**
	 * Before breadcrumb callback for Sentry breadcrumbs.
	 *
	 * Filters and scrubs sensitive data from breadcrumbs before they are added to scope.
	 * This prevents sensitive data from being captured in breadcrumbs (e.g., API requests).
	 *
	 * @since 1.0.0
	 *
	 * @param \Sentry\Breadcrumb $breadcrumb Breadcrumb to filter.
	 *
	 * @return \Sentry\Breadcrumb|null Breadcrumb to add, or null to discard.
	 */
	public static function before_breadcrumb( \Sentry\Breadcrumb $breadcrumb ): ?\Sentry\Breadcrumb {
		// Scrub sensitive data from breadcrumb metadata.
		$metadata          = $breadcrumb->getMetadata();
		$scrubbed_metadata = $metadata;

		if ( is_array( $metadata ) && ! empty( $metadata ) ) {
			$scrubbed_metadata = self::scrub_sensitive_data( $metadata );
		}

		// Filter out breadcrumbs that might contain sensitive data.
		$category = $breadcrumb->getCategory();
		if ( 'http' === $category ) {
			// For HTTP breadcrumbs, ensure query strings are scrubbed.
			if ( isset( $scrubbed_metadata['url'] ) && is_string( $scrubbed_metadata['url'] ) ) {
				$url_parts = wp_parse_url( $scrubbed_metadata['url'] );
				if ( isset( $url_parts['query'] ) ) {
					parse_str( $url_parts['query'], $query_params );
					if ( is_array( $query_params ) ) {
						$query_params             = self::scrub_sensitive_data( $query_params );
						$url_parts['query']       = http_build_query( $query_params );
						$scrubbed_metadata['url'] = self::build_url( $url_parts );
					}
				}
			}
		}

		// Breadcrumb is immutable - create new instance with scrubbed metadata.
		// Only create new instance if metadata was actually changed.
		if ( $scrubbed_metadata !== $metadata ) {
			$scrubbed_breadcrumb = new \Sentry\Breadcrumb(
				$breadcrumb->getLevel(),
				$breadcrumb->getType(),
				$breadcrumb->getCategory(),
				$breadcrumb->getMessage(),
				$scrubbed_metadata,
				$breadcrumb->getTimestamp()
			);
			return $scrubbed_breadcrumb;
		}

		return $breadcrumb;
	}

	/**
	 * Build URL from parsed URL parts.
	 *
	 * Helper function to rebuild URL from parse_url() result.
	 *
	 * @since 1.0.0
	 *
	 * @param array $parts Parsed URL parts from parse_url().
	 *
	 * @return string Reconstructed URL.
	 */
	private static function build_url( array $parts ): string {
		$url = '';

		if ( isset( $parts['scheme'] ) ) {
			$url .= $parts['scheme'] . '://';
		}

		if ( isset( $parts['host'] ) ) {
			$url .= $parts['host'];
		}

		if ( isset( $parts['port'] ) ) {
			$url .= ':' . $parts['port'];
		}

		if ( isset( $parts['path'] ) ) {
			$url .= $parts['path'];
		}

		if ( isset( $parts['query'] ) ) {
			$url .= '?' . $parts['query'];
		}

		if ( isset( $parts['fragment'] ) ) {
			$url .= '#' . $parts['fragment'];
		}

		return $url;
	}

	/**
	 * Scrub sensitive data from array.
	 *
	 * Recursively removes sensitive keys like API keys, tokens, passwords.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Data to scrub.
	 *
	 * @return array Scrubbed data.
	 */
	private static function scrub_sensitive_data( array $data ): array {
		$sensitive_keys = array(
			// API credentials.
			'api_key',
			'api_token',
			'apiKey',
			'apiToken',
			'access_token',
			'accessToken',
			'secret',
			'secret_key',
			'secretKey',
			// Authentication.
			'password',
			'passwd',
			'pwd',
			'auth',
			'authorization',
			'authorization_header',
			'bearer',
			'token',
			// Sentry.
			'dsn',
			'sentry_dsn',
			// Webhooks.
			'webhook_secret',
			'webhookSecret',
			'webhook_key',
			'webhookKey',
			// Cloudflare/Bunny specific.
			'account_id',
			'accountId',
			'library_id',
			'libraryId',
			'collection_id',
			'collectionId',
			// WordPress specific.
			'nonce',
			'wp_nonce',
			// Common sensitive fields.
			'private_key',
			'privateKey',
			'public_key',
			'publicKey',
			'session',
			'session_id',
			'sessionId',
			'cookie',
			'csrf',
			'csrf_token',
			'csrfToken',
		);

		foreach ( $data as $key => $value ) {
			$lower_key = strtolower( $key );

			// Check if key contains sensitive words.
			$is_sensitive = false;
			foreach ( $sensitive_keys as $sensitive ) {
				if ( strpos( $lower_key, strtolower( $sensitive ) ) !== false ) {
					$is_sensitive = true;
					break;
				}
			}

			if ( $is_sensitive ) {
				// Replace with masked value.
				if ( is_string( $value ) && strlen( $value ) > 4 ) {
					$data[ $key ] = '***' . substr( $value, -4 );
				} else {
					$data[ $key ] = '***REDACTED***';
				}
			} elseif ( is_array( $value ) ) {
				// Recursively scrub nested arrays.
				$data[ $key ] = self::scrub_sensitive_data( $value );
			}
		}

		return $data;
	}

	/**
	 * Capture an exception to Sentry.
	 *
	 * Sends exception to Sentry for tracking. Safe to call even if
	 * Sentry is not initialized - will fail silently.
	 *
	 * @since 1.0.0
	 *
	 * @param \Throwable $exception Exception to capture.
	 *
	 * @return void
	 */
	public static function capture_exception( \Throwable $exception ): void {
		if ( ! self::$initialized ) {
			return;
		}

		try {
			captureException( $exception );
		} catch ( \Exception $e ) {
			// Silently fail - don't break plugin if Sentry fails.
			error_log( '[FCHub Stream] Failed to capture exception to Sentry: ' . $e->getMessage() );
		}
	}

	/**
	 * Capture a message to Sentry.
	 *
	 * Sends message to Sentry for tracking. Safe to call even if
	 * Sentry is not initialized - will fail silently.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Message to capture.
	 * @param string $level   Severity level (debug, info, warning, error, fatal).
	 *
	 * @return void
	 */
	public static function capture_message( string $message, string $level = 'info' ): void {
		if ( ! self::$initialized ) {
			return;
		}

		try {
			// Convert string level to Severity enum.
			$severity = match ( $level ) {
				'debug'   => Severity::debug(),
				'info'    => Severity::info(),
				'warning' => Severity::warning(),
				'error'   => Severity::error(),
				'fatal'   => Severity::fatal(),
				default   => Severity::info(),
			};

			captureMessage( $message, $severity );
		} catch ( \Exception $e ) {
			// Silently fail - don't break plugin if Sentry fails.
			error_log( '[FCHub Stream] Failed to capture message to Sentry: ' . $e->getMessage() );
		}
	}

	/**
	 * Check if Sentry is initialized.
	 *
	 * Returns whether Sentry error tracking is currently active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if initialized, false otherwise.
	 */
	public static function is_initialized(): bool {
		return self::$initialized;
	}

	/**
	 * Test Sentry connection.
	 *
	 * Sends a test exception to verify Sentry is working correctly.
	 * Note: All exceptions are caught internally and returned as error status.
	 *
	 * @since 1.0.0
	 *
	 * @return array {
	 *     Test result.
	 *
	 *     @type string $status  Status (success or error).
	 *     @type string $message Status message.
	 * }
	 * @throws \Exception Test exception (caught internally).
	 */
	public static function test_connection(): array {
		$config = self::get_config();

		if ( empty( $config['dsn'] ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Sentry DSN is not configured.', 'fchub-stream' ),
			);
		}

		// Try to initialize if not already done.
		if ( ! self::$initialized ) {
			$initialized = self::init();
			if ( ! $initialized ) {
				return array(
					'status'  => 'error',
					'message' => __( 'Failed to initialize Sentry. Check DSN format.', 'fchub-stream' ),
				);
			}
		}

		try {
			// Create and capture a test exception (what Sentry verification expects).
			try {
				throw new \Exception( 'Test exception from FCHub Stream - Sentry connection verification' );
			} catch ( \Throwable $exception ) {
				// Use Sentry function directly to bypass our wrapper's initialized check.
				captureException( $exception );
			}

			// Also send a test message for good measure.
			captureMessage( 'Sentry connection test from FCHub Stream', Severity::info() );

			// Force flush to ensure events are sent immediately.
			$client = SentrySdk::getCurrentHub()->getClient();
			if ( $client ) {
				$client->flush( 5000 ); // Wait up to 5 seconds for events to be sent.
			}

			return array(
				'status'  => 'success',
				'message' => __( 'Sentry connection test successful. Check your Sentry dashboard for the test exception.', 'fchub-stream' ),
			);
		} catch ( \Exception $e ) {
			return array(
				'status'  => 'error',
				'message' => sprintf(
					/* translators: %s: Error message */
					__( 'Sentry connection test failed: %s', 'fchub-stream' ),
					$e->getMessage()
				),
			);
		}
	}

	/**
	 * Add breadcrumb to Sentry.
	 *
	 * Adds a breadcrumb (event log) to provide context for errors.
	 * Breadcrumbs are shown in Sentry before an error occurs.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message  Breadcrumb message.
	 * @param string $category Category (e.g., 'video.upload', 'api.request').
	 * @param string $level    Level (debug, info, warning, error). Default 'info'.
	 * @param array  $data     Optional additional data. Default empty array.
	 *
	 * @return void
	 */
	public static function add_breadcrumb( string $message, string $category, string $level = 'info', array $data = array() ): void {
		if ( ! self::$initialized ) {
			return;
		}

		try {
			// Sentry SDK v4 API: addBreadcrumb($category, $message, $metadata, $level, $type, $timestamp).
			addBreadcrumb(
				$category,  // Category string.
				$message,   // Message string.
				$data,      // Metadata array.
				$level      // Level string (will be converted by SDK).
			);
		} catch ( \Exception $e ) {
			// Silently fail - don't break plugin if Sentry fails.
			error_log( '[FCHub Stream] Failed to add breadcrumb to Sentry: ' . $e->getMessage() );
		}
	}

	/**
	 * Start a Sentry transaction for tracing.
	 *
	 * Creates a new transaction for performance monitoring.
	 * Remember to call finish() on the returned transaction when done.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Transaction name (e.g., 'video.upload').
	 * @param string $op   Operation name (e.g., 'http.server').
	 * @param array  $data Optional additional data. Default empty array.
	 *
	 * @return \Sentry\Tracing\Transaction|null Transaction object, or null if not initialized.
	 */
	public static function start_transaction( string $name, string $op = 'http.server', array $data = array() ) {
		if ( ! self::$initialized ) {
			return null;
		}

		try {
			// Use TransactionContext (not SpanContext) for startTransaction().
			$context = new TransactionContext();
			$context->setOp( $op );
			$context->setData( $data );
			$context->setName( $name );

			$transaction = startTransaction( $context );

			return $transaction;
		} catch ( \Exception $e ) {
			// Silently fail - don't break plugin if Sentry fails.
			error_log( '[FCHub Stream] Failed to start transaction in Sentry: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Create a child span within a transaction.
	 *
	 * Adds a span to measure a specific operation within a transaction.
	 *
	 * @since 1.0.0
	 *
	 * @param \Sentry\Tracing\Transaction|null $transaction Parent transaction.
	 * @param string                           $op Operation name (e.g., 'http.client', 'db.query').
	 * @param string                           $description Span description.
	 * @param array                            $data Optional additional data. Default empty array.
	 *
	 * @return \Sentry\Tracing\Span|null Span object, or null if transaction is null.
	 */
	public static function start_span( $transaction, string $op, string $description, array $data = array() ) {
		if ( ! $transaction || ! self::$initialized ) {
			return null;
		}

		try {
			$context = new SpanContext();
			$context->setOp( $op );
			$context->setDescription( $description );
			$context->setData( $data );

			return $transaction->startChild( $context );
		} catch ( \Exception $e ) {
			// Silently fail - don't break plugin if Sentry fails.
			error_log( '[FCHub Stream] Failed to start span in Sentry: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Set custom tags for better filtering in Sentry.
	 *
	 * Adds tags to current scope for filtering events.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tags Key-value pairs of tags.
	 *
	 * @return void
	 */
	public static function set_tags( array $tags ): void {
		if ( ! self::$initialized ) {
			return;
		}

		try {
			\Sentry\configureScope(
				function ( Scope $scope ) use ( $tags ): void {
					foreach ( $tags as $key => $value ) {
						$scope->setTag( $key, (string) $value );
					}
				}
			);
		} catch ( \Exception $e ) {
			// Silently fail.
			error_log( '[FCHub Stream] Failed to set Sentry tags: ' . $e->getMessage() );
		}
	}

	/**
	 * Set extra context data for Sentry.
	 *
	 * Adds extra context to current scope.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Context key.
	 * @param mixed  $value Context value.
	 *
	 * @return void
	 */
	public static function set_context( string $key, $value ): void {
		if ( ! self::$initialized ) {
			return;
		}

		try {
			\Sentry\configureScope(
				function ( Scope $scope ) use ( $key, $value ): void {
					$scope->setContext( $key, $value );
				}
			);
		} catch ( \Exception $e ) {
			// Silently fail.
			error_log( '[FCHub Stream] Failed to set Sentry context: ' . $e->getMessage() );
		}
	}

	/**
	 * Set fingerprint for error grouping.
	 *
	 * Controls how Sentry groups similar errors together.
	 * Use this to group errors by type rather than stack trace.
	 *
	 * @since 1.0.0
	 *
	 * @param array $fingerprint Fingerprint components.
	 *
	 * @return void
	 */
	public static function set_fingerprint( array $fingerprint ): void {
		if ( ! self::$initialized ) {
			return;
		}

		try {
			\Sentry\configureScope(
				function ( Scope $scope ) use ( $fingerprint ): void {
					$scope->setFingerprint( $fingerprint );
				}
			);
		} catch ( \Exception $e ) {
			// Silently fail.
			error_log( '[FCHub Stream] Failed to set Sentry fingerprint: ' . $e->getMessage() );
		}
	}

	/**
	 * Get file size range for categorization.
	 *
	 * Categorizes file size into ranges for better filtering.
	 *
	 * @since 1.0.0
	 *
	 * @param int $bytes File size in bytes.
	 *
	 * @return string File size range.
	 */
	public static function get_file_size_range( int $bytes ): string {
		$mb = $bytes / 1024 / 1024;

		if ( $mb < 10 ) {
			return '0-10MB';
		} elseif ( $mb < 50 ) {
			return '10-50MB';
		} elseif ( $mb < 100 ) {
			return '50-100MB';
		} elseif ( $mb < 500 ) {
			return '100-500MB';
		} else {
			return '500MB+';
		}
	}
}
