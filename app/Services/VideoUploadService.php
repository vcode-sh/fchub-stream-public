<?php
/**
 * Video Upload Service
 *
 * Orchestrates video upload operations across multiple providers (Cloudflare Stream, Bunny.net Stream).
 * Handles file validation, provider selection, upload coordination, and response formatting.
 *
 * @package FCHubStream
 * @subpackage Services
 * @since 1.0.0
 */

namespace FCHubStream\App\Services;

use WP_Error;
use function FCHubStream\App\Utils\log_debug;
use function FCHubStream\App\Utils\log_error;

/**
 * Video Upload Service class.
 *
 * Coordinates video upload workflow including validation, provider selection,
 * and upload execution. Provides unified interface for uploading to different providers.
 *
 * @since 1.0.0
 */
class VideoUploadService {

	/**
	 * Upload video file to configured provider
	 *
	 * Main upload orchestration method. Validates file, selects provider,
	 * performs upload, and formats response.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Absolute path to video file.
	 * @param string $filename  Original filename.
	 * @param array  $metadata  Optional. Video metadata. Default empty array.
	 *
	 * @return array|WP_Error {
	 *     Upload result on success, WP_Error on failure.
	 *
	 *     @type string $video_id         Video ID from provider.
	 *     @type string $provider         Provider name ('cloudflare_stream' or 'bunny_stream').
	 *     @type string $status           Upload status ('pending' or 'ready').
	 *     @type string $thumbnail_url    Thumbnail URL.
	 *     @type string $player_url       Player iframe URL.
	 *     @type string $html             Player HTML code.
	 *     @type int    $width            Video width.
	 *     @type int    $height           Video height.
	 *     @type bool   $ready_to_stream  Whether video is ready to stream.
	 * }
	 */
	public static function upload( $file_path, $filename, $metadata = array() ) {
		// SECURITY LAYER 3: Check file integrity before upload (Service Layer).
		if ( class_exists( 'FCHubStream\App\Services\TamperDetection' ) ) {
			TamperDetection::check_file_integrity( 'upload_service' );
		}

		// Check license before upload.
		if ( class_exists( 'FCHubStream\App\Services\StreamLicenseManager' ) ) {
			$license = new StreamLicenseManager();

			if ( ! $license->can_upload_video() ) {
				return new WP_Error(
					'license_required',
					__( 'Active FCHub Stream license required for video uploads.', 'fchub-stream' ),
					array( 'status' => 403 )
				);
			}
		}

		$filesize  = file_exists( $file_path ) ? filesize( $file_path ) : 0;
		$extension = pathinfo( $filename, PATHINFO_EXTENSION );

		// Fallback: if extension is empty, try to get it from file_path.
		if ( empty( $extension ) ) {
			$extension = pathinfo( $file_path, PATHINFO_EXTENSION );
		}

		// Normalize extension to lowercase and ensure it's not empty.
		$extension = ! empty( $extension ) ? strtolower( $extension ) : 'unknown';

		// Store upload start time for total time calculation (Unix timestamp).
		$upload_start_time_unix = time();

		// Safely initialize Sentry tracking (don't break upload if Sentry fails).
		$size_range  = 'unknown';
		$transaction = null;
		try {
			if ( class_exists( 'FCHubStream\App\Services\SentryService' ) ) {
				$size_range = SentryService::get_file_size_range( $filesize );

				// Set custom tags for better filtering in Sentry.
				SentryService::set_tags(
					array(
						'file_format'     => $extension, // Already normalized to lowercase.
						'file_size_range' => $size_range,
						'upload_source'   => $metadata['source'] ?? 'post',
					)
				);

				// Start Sentry transaction for tracing.
				$transaction = SentryService::start_transaction(
					'video.upload',
					'video.upload',
					array(
						'filename'   => $filename,
						'filesize'   => $filesize,
						'format'     => $extension,
						'size_range' => $size_range,
					)
				);

				SentryService::add_breadcrumb(
					'Video upload started',
					'user',
					'info',
					array(
						'filename'   => $filename,
						'filesize'   => $filesize,
						'format'     => $extension,
						'size_range' => $size_range,
					)
				);
			}
		} catch ( \Exception $e ) {
			// Silently continue - don't break upload if Sentry fails.
			log_debug( 'Failed to initialize Sentry for upload: ' . $e->getMessage() );
		}

		try {
			// Start timer for upload performance tracking.
			$upload_start_time = microtime( true );

			// Validate file.
			$validation_span = null;
			try {
				if ( class_exists( 'FCHubStream\App\Services\SentryService' ) && $transaction ) {
					$validation_span = SentryService::start_span( $transaction, 'validation', 'Validate video file' );
					SentryService::add_breadcrumb( 'Validating video file', 'video.upload', 'info' );
				}
			} catch ( \Exception $e ) {  // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Silently continue.
			}

			$validation = self::validate_file( $file_path, $filename );

			if ( $validation_span ) {
				$validation_span->finish();
			}

			if ( is_wp_error( $validation ) ) {
				$error_code = $validation->get_error_code();

				// Safely track validation failure in Sentry/PostHog.
				try {
					if ( class_exists( 'FCHubStream\App\Services\SentryService' ) ) {
						SentryService::add_breadcrumb(
							'Validation failed: ' . $validation->get_error_message(),
							'console',
							'error',
							array( 'error_code' => $error_code )
						);

						// Set fingerprint to group similar validation errors together.
						SentryService::set_fingerprint(
							array(
								'{{ default }}',
								'validation-error',
								$error_code,
							)
						);

						// Capture validation errors to Sentry for analysis.
						SentryService::capture_message(
							'Video upload validation failed: ' . $validation->get_error_message(),
							'warning'
						);
					}

					// Track validation failure in PostHog.
					if ( class_exists( 'FCHubStream\App\Services\PostHogService' ) && PostHogService::is_initialized() ) {
						$file_size_mb     = round( $filesize / 1024 / 1024, 2 );
						$defaults         = get_option( 'fchub_stream_upload_settings', array() );
						$max_file_size_mb = $defaults['max_file_size'] ?? $defaults['max_file_size_mb'] ?? 500;

						// Ensure format is not empty (use 'unknown' if empty).
						$format_value = ! empty( $extension ) ? $extension : 'unknown';

						PostHogService::track_video_validation_failed(
							$error_code,
							$validation->get_error_message(),
							array(
								'file_size_mb' => $file_size_mb,
								'format'       => $format_value,
								'max_size_mb'  => $max_file_size_mb,
							)
						);
					}
				} catch ( \Exception $e ) {
					// Silently continue - don't break upload if tracking fails.
					log_debug( 'Failed to track validation failure: ' . $e->getMessage() );
				}

				if ( $transaction ) {
					try {
						$transaction->setStatus( \Sentry\Tracing\SpanStatus::invalidArgument() );
						$transaction->finish();
					} catch ( \Exception $e ) {  // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
						// Silently continue.
					}
				}

				return $validation;
			}

			try {
				if ( class_exists( 'FCHubStream\App\Services\SentryService' ) ) {
					SentryService::add_breadcrumb( 'Validation passed', 'console', 'info' );
				}
			} catch ( \Exception $e ) {  // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Silently continue.
			}

			// Get provider configuration.
			$config   = StreamConfigService::get_private();
			$provider = $config['provider'] ?? 'cloudflare';

			try {
				if ( class_exists( 'FCHubStream\App\Services\SentryService' ) ) {
					SentryService::add_breadcrumb(
						'Provider selected: ' . $provider,
						'console',
						'info',
						array( 'provider' => $provider )
					);
				}
			} catch ( \Exception $e ) {  // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Silently continue.
			}

			// Upload to provider.
			$upload_span = null;
			try {
				if ( class_exists( 'FCHubStream\App\Services\SentryService' ) && $transaction ) {
					$upload_span = SentryService::start_span(
						$transaction,
						'http.client',
						'Upload to ' . $provider,
						array( 'provider' => $provider )
					);
				}
			} catch ( \Exception $e ) {  // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Silently continue.
			}

			$upload_api_start_time = microtime( true );

			if ( 'bunny' === $provider ) {
				$result = self::upload_to_bunny( $file_path, $filename, $metadata, $config );
			} else {
				$result = self::upload_to_cloudflare( $file_path, $filename, $metadata, $config );
			}

			$upload_api_end_time = microtime( true );
			$upload_api_time     = $upload_api_end_time - $upload_api_start_time;

			if ( $upload_span ) {
				$upload_span->finish();
			}

			// Capture upload errors to Sentry.
			if ( is_wp_error( $result ) ) {
				$error_code = $result->get_error_code();

				// Safely track upload failure in Sentry/PostHog.
				try {
					if ( class_exists( 'FCHubStream\App\Services\SentryService' ) ) {
						SentryService::add_breadcrumb(
							'Upload failed: ' . $result->get_error_message(),
							'http',
							'error',
							array(
								'provider'   => $provider,
								'error_code' => $error_code,
							)
						);

						// Set fingerprint to group similar upload errors.
						SentryService::set_fingerprint(
							array(
								'{{ default }}',
								'upload-error',
								$provider,
								$error_code,
							)
						);

						SentryService::capture_message(
							sprintf(
								'Video upload failed to %s: %s',
								$provider,
								$result->get_error_message()
							),
							'error'
						);
					}

					// Track upload failure in PostHog.
					if ( class_exists( 'FCHubStream\App\Services\PostHogService' ) && PostHogService::is_initialized() ) {
						$file_size_mb      = round( $filesize / 1024 / 1024, 2 );
						$upload_end_time   = microtime( true );
						$total_upload_time = $upload_end_time - $upload_start_time;

						// Ensure format is not empty (use 'unknown' if empty).
						$format_value = ! empty( $extension ) ? $extension : 'unknown';

						// Map internal provider name to tracking name with _stream suffix for consistency.
						$provider_value = ! empty( $provider ) ? $provider . '_stream' : 'unknown';

						PostHogService::track_video_upload_failed(
							$error_code,
							$result->get_error_message(),
							$provider_value,
							array(
								'file_size_mb'        => $file_size_mb,
								'format'              => $format_value,
								'upload_time_seconds' => round( $total_upload_time, 2 ),
								'upload_time_ms'      => round( $total_upload_time * 1000, 2 ),
							)
						);
					}
				} catch ( \Exception $e ) {
					// Silently continue - don't break upload if tracking fails.
					log_debug( 'Failed to track upload failure: ' . $e->getMessage() );
				}

				if ( $transaction ) {
					try {
						$transaction->setStatus( \Sentry\Tracing\SpanStatus::internalError() );
						$transaction->finish();
					} catch ( \Exception $e ) {  // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
						// Silently continue.
					}
				}

				return $result;
			}

			// Safely track successful upload.
			try {
				if ( class_exists( 'FCHubStream\App\Services\SentryService' ) ) {
					SentryService::add_breadcrumb(
						'Upload successful',
						'http',
						'info',
						array(
							'video_id' => $result['video_id'] ?? 'unknown',
							'provider' => $provider,
						)
					);
				}

				// Track successful upload in PostHog.
				if ( class_exists( 'FCHubStream\App\Services\PostHogService' ) && PostHogService::is_initialized() ) {
					$file_size_mb      = round( $filesize / 1024 / 1024, 2 );
					$upload_end_time   = microtime( true );
					$total_upload_time = $upload_end_time - $upload_start_time;

					// Ensure format is not empty (use 'unknown' if empty).
					$format_value = ! empty( $extension ) ? $extension : 'unknown';

					// Map internal provider name to tracking name with _stream suffix for consistency.
					$provider_value = ! empty( $provider ) ? $provider . '_stream' : 'unknown';

					PostHogService::track_video_upload(
						array(
							'provider'         => $provider_value,
							'file_size_mb'     => $file_size_mb,
							'duration_seconds' => $metadata['duration'] ?? 0,
							'format'           => $format_value,
							'source'           => $metadata['source'] ?? 'post',
						)
					);

					// Track upload time performance.
					PostHogService::track_upload_time(
						$total_upload_time,
						$provider_value, // Already has _stream suffix from above.
						array(
							'file_size_mb'      => $file_size_mb,
							'format'            => $format_value,
							'video_id'          => $result['video_id'] ?? 'unknown',
							'upload_start_time' => $upload_start_time_unix, // Pass Unix timestamp for consistency.
						)
					);

					// Store upload timestamp for encoding time calculation (expires in 14 days).
					// Also store upload_start_time in transient for total time calculation.
					$video_id = $result['video_id'] ?? '';
					if ( ! empty( $video_id ) ) {
						set_transient( 'fchub_stream_upload_time_' . $video_id, $upload_start_time_unix, 14 * DAY_IN_SECONDS );
						// Also store upload_time_seconds for total time calculation.
						set_transient( 'fchub_stream_upload_duration_' . $video_id, $total_upload_time, 14 * DAY_IN_SECONDS );
					}
				}

				if ( $transaction ) {
					try {
						$transaction->setStatus( \Sentry\Tracing\SpanStatus::ok() );
						$transaction->finish();
					} catch ( \Exception $e ) {  // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
						// Silently continue.
					}
				}
			} catch ( \Exception $e ) {
				// Silently continue - don't break upload if tracking fails.
				log_debug( 'Failed to track successful upload: ' . $e->getMessage() );
			}

			return $result;
		} catch ( \Exception $e ) {
			// Safely capture exceptions to Sentry.
			try {
				if ( class_exists( 'FCHubStream\App\Services\SentryService' ) ) {
					SentryService::add_breadcrumb(
						'Exception: ' . $e->getMessage(),
						'video.upload',
						'error'
					);

					// Capture exceptions to Sentry.
					SentryService::capture_exception( $e );
				}

				if ( $transaction ) {
					try {
						$transaction->setStatus( \Sentry\Tracing\SpanStatus::internalError() );
						$transaction->finish();
					} catch ( \Exception $e2 ) {  // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
						// Silently continue.
					}
				}
			} catch ( \Exception $e2 ) {
				// Silently continue - don't break exception handling if Sentry fails.
				error_log( '[FCHub Stream] Failed to capture exception to Sentry: ' . $e2->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			return new WP_Error(
				'upload_exception',
				sprintf(
					/* translators: %s: Error message */
					__( 'Video upload failed: %s', 'fchub-stream' ),
					$e->getMessage()
				),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Upload to Cloudflare Stream
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param string $file_path Absolute path to video file.
	 * @param string $filename  Original filename.
	 * @param array  $metadata  Video metadata.
	 * @param array  $config    Provider configuration.
	 *
	 * @return array|WP_Error Upload result or error.
	 */
	private static function upload_to_cloudflare( $file_path, $filename, $metadata, $config ) {
		$cloudflare = $config['cloudflare'] ?? array();

		if ( empty( $cloudflare['account_id'] ) || empty( $cloudflare['api_token'] ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'Cloudflare Stream credentials not configured.', 'fchub-stream' ),
				array( 'status' => 400 )
			);
		}

		// Create API service.
		$api = new CloudflareApiService(
			$cloudflare['account_id'],
			$cloudflare['api_token']
		);

		// Upload video.
		$result = $api->upload_video( $file_path, $filename, $metadata );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Set allowedOrigins to allow video playback from WordPress site domain.
		// This prevents 500 errors when other users try to view the video.
		$video_uid = $result['uid'] ?? '';
		if ( ! empty( $video_uid ) ) {
			// Get WordPress site domain (without protocol).
			$site_url = wp_parse_url( home_url(), PHP_URL_HOST );
			if ( $site_url ) {
				// Remove www. prefix if present (Cloudflare Stream handles both).
				$allowed_origins = array( $site_url );
				if ( strpos( $site_url, 'www.' ) === 0 ) {
					$allowed_origins[] = substr( $site_url, 4 );
				} elseif ( strpos( $site_url, 'www.' ) !== 0 ) {
					$allowed_origins[] = 'www.' . $site_url;
				}

				// Update video with allowedOrigins.
				$update_result = $api->update_video(
					$video_uid,
					array(
						'allowedOrigins' => $allowed_origins,
					)
				);

				if ( is_wp_error( $update_result ) ) {
					// Log error but don't fail upload - video was uploaded successfully.
					error_log( '[FCHub Stream] Failed to set allowedOrigins for video ' . $video_uid . ': ' . $update_result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

					// Track in Sentry.
					try {
						if ( class_exists( 'FCHubStream\App\Services\SentryService' ) ) {
							SentryService::capture_message(
								'Failed to set allowedOrigins for Cloudflare Stream video. Video ID: ' . $video_uid . ', Error: ' . $update_result->get_error_message(),
								'warning',
								array(
									'context' => array(
										'component'       => 'video_upload',
										'provider'        => 'cloudflare',
										'video_id'        => $video_uid,
										'allowed_origins' => $allowed_origins,
										'error_message'   => $update_result->get_error_message(),
									),
								)
							);
						}
					} catch ( \Exception $e ) {
						// Silently continue.
					}
				} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( '[FCHub Stream] Successfully set allowedOrigins for video ' . $video_uid . ': ' . wp_json_encode( $allowed_origins ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
		}

		// Format response.
		return self::format_cloudflare_response( $result, $cloudflare );
	}

	/**
	 * Upload to Bunny.net Stream
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param string $file_path Absolute path to video file.
	 * @param string $filename  Original filename.
	 * @param array  $metadata  Video metadata.
	 * @param array  $config    Provider configuration.
	 *
	 * @return array|WP_Error Upload result or error.
	 */
	private static function upload_to_bunny( $file_path, $filename, $metadata, $config ) {
		$bunny = $config['bunny'] ?? array();

		if ( empty( $bunny['library_id'] ) || empty( $bunny['api_key'] ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'Bunny.net Stream credentials not configured.', 'fchub-stream' ),
				array( 'status' => 400 )
			);
		}

		// Create API service (Account API Key needed for constructor).
		$api = new BunnyApiService(
			$bunny['api_key'], // Using api_key as both account and stream key for simplicity.
			$bunny['api_key'],
			(int) $bunny['library_id']
		);

		// Upload video.
		$result = $api->upload_video(
			$file_path,
			$filename,
			$metadata,
			(int) $bunny['library_id'],
			$bunny['api_key']
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Format response.
		return self::format_bunny_response( $result, $bunny );
	}

	/**
	 * Validate video file
	 *
	 * Validates file exists, size, and format against configuration limits.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Absolute path to video file.
	 * @param string $filename  Original filename (used to extract extension).
	 *
	 * @return true|WP_Error True if valid, WP_Error on validation failure.
	 */
	public static function validate_file( $file_path, $filename = '' ) {
		// Check file exists.
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error(
				'file_not_found',
				__( 'Video file not found.', 'fchub-stream' ),
				array( 'status' => 400 )
			);
		}

		// Get upload settings.
		// Use same source as frontend - get_option('fchub_stream_upload_settings').
		$defaults = get_option( 'fchub_stream_upload_settings', array() );
		// Support both max_file_size and max_file_size_mb for backward compatibility.
		$max_file_size_mb     = $defaults['max_file_size'] ?? $defaults['max_file_size_mb'] ?? 500;
		$allowed_formats      = $defaults['allowed_formats'] ?? array( 'mp4', 'mov', 'webm', 'avi' );
		$max_duration_seconds = $defaults['max_duration_seconds'] ?? 0; // 0 = unlimited.

		error_log( '[FCHub Stream] Validation: Settings from get_option - max_file_size_mb: ' . $max_file_size_mb . ', allowed_formats: ' . print_r( $allowed_formats, true ) . ', max_duration_seconds: ' . $max_duration_seconds ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r

		// Check file size.
		$file_size_mb = filesize( $file_path ) / 1024 / 1024;
		error_log( '[FCHub Stream] Validation: File size: ' . $file_size_mb . 'MB' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		if ( $file_size_mb > $max_file_size_mb ) {
			error_log( '[FCHub Stream] Validation: File too large - ' . $file_size_mb . 'MB > ' . $max_file_size_mb . 'MB' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %d: Maximum file size in MB */
					__( 'File size exceeds maximum allowed size (%dMB).', 'fchub-stream' ),
					$max_file_size_mb
				),
				array( 'status' => 400 )
			);
		}

		// Check file format.
		// Use filename if provided (for temp files without extension), otherwise use file_path.
		$source_for_extension = ! empty( $filename ) ? $filename : $file_path;
		$file_extension       = strtolower( pathinfo( $source_for_extension, PATHINFO_EXTENSION ) );
		error_log( '[FCHub Stream] Validation: File extension from "' . $source_for_extension . '": ' . $file_extension ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		if ( ! in_array( $file_extension, $allowed_formats, true ) ) {
			error_log( '[FCHub Stream] Validation: Extension not allowed. Extension: ' . $file_extension . ', Allowed: ' . print_r( $allowed_formats, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r
			return new WP_Error(
				'invalid_format',
				sprintf(
					/* translators: %s: Comma-separated list of allowed formats */
					__( 'File format not allowed. Allowed formats: %s', 'fchub-stream' ),
					implode( ', ', $allowed_formats )
				),
				array( 'status' => 400 )
			);
		}

		error_log( '[FCHub Stream] Validation: File validated successfully' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		// Check MIME type.
		$finfo     = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type = finfo_file( $finfo, $file_path );
		finfo_close( $finfo );

		$allowed_mime_types = array(
			'video/mp4',
			'video/quicktime',
			'video/webm',
			'video/x-msvideo',
		);

		if ( ! in_array( $mime_type, $allowed_mime_types, true ) ) {
			return new WP_Error(
				'invalid_mime_type',
				__( 'Invalid video file type.', 'fchub-stream' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Format Cloudflare response
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param array $result    API response from Cloudflare.
	 * @param array $cloudflare Cloudflare configuration.
	 *
	 * @return array Formatted response.
	 */
	private static function format_cloudflare_response( $result, $cloudflare ) {
		$video_id = $result['uid'] ?? '';
		$status   = $result['status']['state'] ?? 'pending';
		$ready    = $result['readyToStream'] ?? false;

		// Extract customer subdomain from playback URL.
		$customer_subdomain = '';
		if ( isset( $result['playback']['hls'] ) ) {
			// Example: https://customer-efgtuz47vfed9702.cloudflarestream.com/VIDEO_ID/manifest/video.m3u8.
			$hls_url = $result['playback']['hls'];
			if ( preg_match( '/https?:\/\/(customer-[a-z0-9]+)\.cloudflarestream\.com/', $hls_url, $matches ) ) {
				$customer_subdomain = $matches[1];
				error_log( '[FCHub Stream] Extracted customer_subdomain from HLS URL: ' . $customer_subdomain ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		// Fallback to account_id if extraction failed (shouldn't happen).
		if ( empty( $customer_subdomain ) ) {
			$account_id         = $cloudflare['account_id'] ?? '';
			$customer_subdomain = "customer-{$account_id}";
			error_log( '[FCHub Stream] WARNING: Could not extract customer_subdomain from playback URL, using fallback: ' . $customer_subdomain ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		// Generate player URL and HTML.
		$player_url = "https://{$customer_subdomain}.cloudflarestream.com/{$video_id}/iframe";

		// CRITICAL: Only mark as ready if video has playback URLs AND pctComplete is 100.
		// Cloudflare may return readyToStream=true with playback URLs BUT pctComplete < 100.
		// In this case, manifest URLs return 404 until pctComplete reaches 100.
		// Reference: https://developers.cloudflare.com/stream/manage-video-library/using-webhooks.
		$pct_complete = floatval( $result['status']['pctComplete'] ?? 0 );
		$actual_ready = $ready && isset( $result['playback']['hls'] ) && ! empty( $result['playback']['hls'] ) && $pct_complete >= 100;

		// Log pctComplete for debugging (only if video appears ready).
		if ( $ready && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[FCHub Stream] Video pctComplete: ' . $pct_complete . '% for video_id: ' . $video_id . ', actual_ready: ' . ( $actual_ready ? 'YES' : 'NO' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		$player_html = '';
		if ( $actual_ready ) {
			// Full HTML with wrapper div matching PortalIntegration format.
			$player_html = sprintf(
				'<div class="fchub-stream-player-wrapper" data-video-id="%s" data-provider="cloudflare_stream" style="position: relative; padding-bottom: 56.25%%; height: 0; overflow: hidden; margin: 0 !important;">
					<iframe
						src="%s"
						style="position: absolute; top: 0; left: 0; width: 100%%; height: 100%%; border: 0;"
						allow="accelerometer; gyroscope; autoplay; encrypted-media;"
						allowfullscreen="true">
					</iframe>
				</div>',
				esc_attr( $video_id ),
				esc_url( $player_url )
			);
		}

		$thumbnail_url = $result['thumbnail'] ?? '';

		return array(
			'video_id'           => $video_id,
			'provider'           => 'cloudflare_stream',
			'status'             => $actual_ready ? 'ready' : 'pending',
			'thumbnail_url'      => $thumbnail_url,
			'player_url'         => $player_url,
			'html'               => $player_html,
			'width'              => 1920,
			'height'             => 1080,
			'readyToStream'      => $actual_ready,
			'ready_to_stream'    => $actual_ready, // Keep both for compatibility.
			'customer_subdomain' => $customer_subdomain,
		);
	}

	/**
	 * Format Bunny response
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param array $result API response from Bunny.net.
	 * @param array $bunny  Bunny.net configuration.
	 *
	 * @return array Formatted response.
	 */
	private static function format_bunny_response( $result, $bunny ) {
		$video_id   = $result['guid'] ?? '';
		$status_int = $result['status'] ?? 0;
		$ready      = ( 5 === $status_int );

		$library_id = $bunny['library_id'] ?? '';

		// Generate player URL and HTML.
		$player_url  = "https://iframe.mediadelivery.net/embed/{$library_id}/{$video_id}?autoplay=false";
		$player_html = sprintf(
			'<iframe src="%s" style="border: none; width: 100%%; aspect-ratio: 16/9;" allow="accelerometer; autoplay; encrypted-media; gyroscope;" allowfullscreen="true"></iframe>',
			esc_url( $player_url )
		);

		// Bunny.net thumbnail URL pattern (will be available after encoding).
		$thumbnail_url = '';
		if ( ! empty( $result['thumbnailFileName'] ) ) {
			// Zone ID would need to be configured separately.
			// For now, leave empty and update via webhook.
			$thumbnail_url = ''; // Will be updated after encoding.
		}

		return array(
			'video_id'        => $video_id,
			'provider'        => 'bunny_stream',
			'status'          => $ready ? 'ready' : 'pending',
			'thumbnail_url'   => $thumbnail_url,
			'player_url'      => $player_url,
			'html'            => $player_html,
			'width'           => 1920,
			'height'          => 1080,
			'ready_to_stream' => $ready,
		);
	}

	/**
	 * Get video status
	 *
	 * Checks encoding status of uploaded video.
	 *
	 * @since 1.0.0
	 *
	 * @param string $video_id Video ID from provider.
	 * @param string $provider Provider name ('cloudflare_stream' or 'bunny_stream').
	 *
	 * @return array|WP_Error Status information or error.
	 */
	public static function get_video_status( $video_id, $provider ) {
		$config = StreamConfigService::get_private();

		if ( 'bunny_stream' === $provider ) {
			return self::get_bunny_video_status( $video_id, $config );
		}

		return self::get_cloudflare_video_status( $video_id, $config );
	}

	/**
	 * Get Cloudflare video status
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param string $video_id Video UID.
	 * @param array  $config   Provider configuration.
	 *
	 * @return array|WP_Error Status information or error.
	 */
	private static function get_cloudflare_video_status( $video_id, $config ) {
		$cloudflare = $config['cloudflare'] ?? array();

		if ( empty( $cloudflare['account_id'] ) || empty( $cloudflare['api_token'] ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'Cloudflare Stream credentials not configured.', 'fchub-stream' ),
				array( 'status' => 400 )
			);
		}

		$api = new CloudflareApiService(
			$cloudflare['account_id'],
			$cloudflare['api_token']
		);

		$result = $api->get_video( $video_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::format_cloudflare_response( $result, $cloudflare );
	}

	/**
	 * Get Bunny video status
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param string $video_id Video GUID.
	 * @param array  $config   Provider configuration.
	 *
	 * @return array|WP_Error Status information or error.
	 */
	private static function get_bunny_video_status( $video_id, $config ) {
		$bunny = $config['bunny'] ?? array();

		if ( empty( $bunny['library_id'] ) || empty( $bunny['api_key'] ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'Bunny.net Stream credentials not configured.', 'fchub-stream' ),
				array( 'status' => 400 )
			);
		}

		// For Bunny.net, we need to call GET /library/{library_id}/videos/{video_id}.
		// This requires adding a method to BunnyApiService.
		// For now, return a not implemented error.
		return new WP_Error(
			'not_implemented',
			__( 'Bunny.net video status check not yet implemented.', 'fchub-stream' ),
			array( 'status' => 501 )
		);
	}

	/**
	 * Generate player HTML
	 *
	 * Generates iframe HTML for video player based on provider and video ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $video_id Video ID from provider.
	 * @param string $provider Provider name ('cloudflare_stream' or 'bunny_stream').
	 *
	 * @return string|WP_Error Player HTML or error.
	 */
	public static function generate_player_html( $video_id, $provider ) {
		$config = StreamConfigService::get_public();

		if ( 'bunny_stream' === $provider ) {
			$bunny      = $config['bunny'] ?? array();
			$library_id = $bunny['library_id'] ?? '';

			if ( empty( $library_id ) ) {
				return new WP_Error(
					'missing_config',
					__( 'Bunny.net library ID not configured.', 'fchub-stream' ),
					array( 'status' => 400 )
				);
			}

			$player_url = "https://iframe.mediadelivery.net/embed/{$library_id}/{$video_id}?autoplay=false";

			return sprintf(
				'<iframe src="%s" style="border: none; width: 100%%; aspect-ratio: 16/9;" allow="accelerometer; autoplay; encrypted-media; gyroscope;" allowfullscreen="true"></iframe>',
				esc_url( $player_url )
			);
		}

		// Cloudflare Stream.
		$cloudflare = $config['cloudflare'] ?? array();
		$account_id = $cloudflare['account_id'] ?? '';

		if ( empty( $account_id ) ) {
			return new WP_Error(
				'missing_config',
				__( 'Cloudflare account ID not configured.', 'fchub-stream' ),
				array( 'status' => 400 )
			);
		}

		$player_url = "https://customer-{$account_id}.cloudflarestream.com/{$video_id}/iframe";

		return sprintf(
			'<iframe src="%s" style="border: none; width: 100%%; aspect-ratio: 16/9;" allow="accelerometer; gyroscope; autoplay; encrypted-media;" allowfullscreen="true"></iframe>',
			esc_url( $player_url )
		);
	}
}
