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
		$filesize   = file_exists( $file_path ) ? filesize( $file_path ) : 0;
		$extension  = pathinfo( $filename, PATHINFO_EXTENSION );
		$size_range = SentryService::get_file_size_range( $filesize );

		// Set custom tags for better filtering in Sentry.
		SentryService::set_tags(
			array(
				'file_format'     => strtolower( $extension ),
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

		try {
			// Validate file.
			$validation_span = SentryService::start_span( $transaction, 'validation', 'Validate video file' );

			SentryService::add_breadcrumb( 'Validating video file', 'video.upload', 'info' );

			$validation = self::validate_file( $file_path, $filename );

			if ( $validation_span ) {
				$validation_span->finish();
			}

			if ( is_wp_error( $validation ) ) {
				$error_code = $validation->get_error_code();

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

				if ( $transaction ) {
					$transaction->setStatus( 'invalid_argument' );
					$transaction->finish();
				}

				return $validation;
			}

			SentryService::add_breadcrumb( 'Validation passed', 'console', 'info' );

			// Get provider configuration.
			$config   = StreamConfigService::get_private();
			$provider = $config['provider'] ?? 'cloudflare';

			SentryService::add_breadcrumb(
				'Provider selected: ' . $provider,
				'console',
				'info',
				array( 'provider' => $provider )
			);

			// Upload to provider.
			$upload_span = SentryService::start_span(
				$transaction,
				'http.client',
				'Upload to ' . $provider,
				array( 'provider' => $provider )
			);

			if ( 'bunny' === $provider ) {
				$result = self::upload_to_bunny( $file_path, $filename, $metadata, $config );
			} else {
				$result = self::upload_to_cloudflare( $file_path, $filename, $metadata, $config );
			}

			if ( $upload_span ) {
				$upload_span->finish();
			}

			// Capture upload errors to Sentry.
			if ( is_wp_error( $result ) ) {
				$error_code = $result->get_error_code();

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

				if ( $transaction ) {
					$transaction->setStatus( 'internal_error' );
					$transaction->finish();
				}

				return $result;
			}

			SentryService::add_breadcrumb(
				'Upload successful',
				'http',
				'info',
				array(
					'video_id' => $result['video_id'] ?? 'unknown',
					'provider' => $provider,
				)
			);

			if ( $transaction ) {
				$transaction->setStatus( 'ok' );
				$transaction->finish();
			}

			return $result;
		} catch ( \Exception $e ) {
			SentryService::add_breadcrumb(
				'Exception: ' . $e->getMessage(),
				'video.upload',
				'error'
			);

			// Capture exceptions to Sentry.
			SentryService::capture_exception( $e );

			if ( $transaction ) {
				$transaction->setStatus( 'internal_error' );
				$transaction->finish();
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

		// Only generate player HTML if video is ready to stream AND has playback URLs.
		// Cloudflare may set readyToStream=true before playback URLs are available.
		$actual_ready = $ready && isset( $result['playback']['hls'] ) && ! empty( $result['playback']['hls'] );
		$player_html  = '';
		if ( $actual_ready ) {
			// Full HTML with wrapper div matching PortalIntegration format.
			$player_html = sprintf(
				'<div class="fchub-stream-player-wrapper" data-video-id="%s" data-provider="cloudflare_stream" style="position: relative; padding-bottom: 56.25%%; height: 0; overflow: hidden; margin: 0 !important;">
					<iframe
						src="%s"
						style="position: absolute; top: 0; left: 0; width: 100%%; height: 100%%; border: 0;"
						allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;"
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
		$player_url  = "https://iframe.mediadelivery.net/embed/{$library_id}/{$video_id}";
		$player_html = sprintf(
			'<iframe src="%s" style="border: none; width: 100%%; aspect-ratio: 16/9;" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture;" allowfullscreen="true"></iframe>',
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

			$player_url = "https://iframe.mediadelivery.net/embed/{$library_id}/{$video_id}";

			return sprintf(
				'<iframe src="%s" style="border: none; width: 100%%; aspect-ratio: 16/9;" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture;" allowfullscreen="true"></iframe>',
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
			'<iframe src="%s" style="border: none; width: 100%%; aspect-ratio: 16/9;" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" allowfullscreen="true"></iframe>',
			esc_url( $player_url )
		);
	}
}
