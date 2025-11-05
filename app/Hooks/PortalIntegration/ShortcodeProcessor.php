<?php
/**
 * Shortcode Processor for FCHub Stream
 *
 * Handles processing of video shortcodes in posts and comments.
 * Integrates with FluentCommunity feed system to display video players.
 *
 * @package FCHubStream
 * @since 1.0.0
 */

namespace FCHubStream\App\Hooks\PortalIntegration;

use FCHubStream\App\Services\StreamConfigService;
use FCHubStream\App\Services\SentryService;

/**
 * Class ShortcodeProcessor
 *
 * Processes video shortcodes before save and during API responses.
 */
class ShortcodeProcessor {
	/**
	 * Player renderer instance
	 *
	 * @var object PlayerRenderer instance
	 */
	private $player_renderer;

	/**
	 * Video validator instance
	 *
	 * @var object VideoValidator instance
	 */
	private $video_validator;

	/**
	 * Constructor
	 *
	 * @param object $player_renderer PlayerRenderer instance for generating player HTML.
	 * @param object $video_validator VideoValidator instance for checking video existence.
	 */
	public function __construct( $player_renderer, $video_validator ) {
		$this->player_renderer = $player_renderer;
		$this->video_validator = $video_validator;
	}

	/**
	 * Register WordPress hooks
	 *
	 * @return void
	 */
	public function register(): void {
		// CRITICAL: Priority 5 runs BEFORE processFeedMetaData (priority 10).
		add_filter( 'fluent_community/feed/new_feed_data', array( $this, 'process_shortcodes_before_save' ), 5, 2 );
		add_action( 'fluent_community/comment_added', array( $this, 'process_comment_media_after_create' ), 5, 2 );
		add_filter( 'fluent_community/feed_api_response', array( $this, 'process_shortcodes_in_response' ), 10, 2 );
		add_filter( 'fluent_community/feeds_api_response', array( $this, 'process_shortcodes_in_feeds' ), 10, 2 );
	}

	/**
	 * Process video shortcodes before saving feed data
	 *
	 * Checks media object first, then falls back to message content.
	 * Creates media_preview meta for video embeds with pending status.
	 *
	 * @param array $data Feed data being saved.
	 * @param array $request_data Original request data from client.
	 * @return array Modified feed data with media_preview meta.
	 */
	public function process_shortcodes_before_save( $data, $request_data ) {
		error_log( '[FCHub Stream] process_shortcodes_before_save() - CALLED' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[FCHub Stream] request_data keys: ' . implode( ', ', array_keys( $request_data ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		if ( isset( $request_data['media'] ) ) {
			error_log( '[FCHub Stream] media object present: ' . wp_json_encode( $request_data['media'] ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		// Only process if data is an array.
		if ( ! is_array( $data ) ) {
			return $data;
		}

		// Check if media object contains our shortcode (from fetch interceptor).
		if ( isset( $request_data['media']['html'] ) && strpos( $request_data['media']['html'], '[fchub_stream:' ) !== false ) {
			error_log( '[FCHub Stream] process_shortcodes_before_save() - Found video in media.html' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			$shortcode = $request_data['media']['html'];
			$pattern   = '/\[fchub_stream:([a-zA-Z0-9_-]+)(?:\s+provider="(cloudflare_stream|bunny_stream)")?\]/';

			if ( preg_match( $pattern, $shortcode, $matches ) ) {
				$video_id = $matches[1];
				$provider = $matches[2] ?? null;

				// Get status from media object (sent from frontend after upload).
				// Default to 'pending' if not provided.
				$status = $request_data['media']['status'] ?? 'pending';

				// Get customer_subdomain from frontend (for Cloudflare encoding overlay).
				$customer_subdomain = $request_data['media']['customer_subdomain'] ?? '';

				error_log( '[FCHub Stream] process_shortcodes_before_save() - Video ID: ' . $video_id . ', Provider: ' . ( $provider ?? 'auto' ) . ', Status: ' . $status . ', Customer subdomain: ' . $customer_subdomain ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

				// Get player HTML with actual status from frontend.
				// Pass customer_subdomain as 4th parameter for encoding overlay.
				$player_html = $this->player_renderer->get_player_html( $video_id, $provider, $status, $customer_subdomain );

				// Get thumbnail.
				$thumbnail_url    = $request_data['media']['image'] ?? '';
				$enabled_provider = $provider ?? StreamConfigService::get_enabled_provider();

				// Note: player_html already includes wrapper div with margin fix from VideoPlayerRenderer.
				// No need to wrap again - just use as-is.

				// Create media_preview.
				$data['meta']['media_preview'] = array(
					'type'         => 'iframe_html',
					'provider'     => $enabled_provider,
					'html'         => $player_html,
					'image'        => $thumbnail_url,
					'video_id'     => $video_id,
					'status'       => $status, // Use status from frontend (pending/ready).
					'content_type' => 'video',
				);

				return $data;
			}
		}

		// Fallback: Check message for shortcode (old method).
		$message          = $data['message'] ?? '';
		$message_rendered = $data['message_rendered'] ?? '';

		// Look for shortcode pattern: [fchub_stream:VIDEO_ID] or [fchub_stream:VIDEO_ID provider="..."].
		$pattern = '/\[fchub_stream:([a-zA-Z0-9_-]+)(?:\s+provider="(cloudflare_stream|bunny_stream)")?\]/';

		if ( preg_match( $pattern, $message_rendered, $matches ) ) {
			$video_id = $matches[1];
			$provider = $matches[2] ?? null;

			error_log( '[FCHub Stream] process_shortcodes_before_save() - Found shortcode: video_id=' . $video_id . ', provider=' . ( $provider ?? 'auto' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			// Get player HTML with pending status (fallback for old shortcodes).
			$player_html = $this->player_renderer->get_player_html( $video_id, $provider, 'pending' );

			// Extract thumbnail URL from player HTML or use default.
			$thumbnail_url    = '';
			$enabled_provider = $provider ?? StreamConfigService::get_enabled_provider();

			if ( 'cloudflare_stream' === $enabled_provider ) {
				$config             = StreamConfigService::get_cloudflare_config();
				$customer_subdomain = $config['customer_subdomain'] ?? '';

				// Normalize customer_subdomain - extract only subdomain part if full URL is provided.
				if ( ! empty( $customer_subdomain ) ) {
					// If contains .cloudflarestream.com, extract just the subdomain part.
					if ( preg_match( '/https?:\/\/(customer-[a-z0-9]+)\.cloudflarestream\.com/', $customer_subdomain, $matches ) ) {
						$customer_subdomain = $matches[1];
					} elseif ( preg_match( '/^(customer-[a-z0-9]+)\.cloudflarestream\.com/', $customer_subdomain, $matches ) ) {
						$customer_subdomain = $matches[1];
					}
					// Otherwise assume it's already just the subdomain (customer-xxx).
				}

				if ( $customer_subdomain ) {
					$thumbnail_url = "https://{$customer_subdomain}.cloudflarestream.com/{$video_id}/thumbnails/thumbnail.jpg";
				}
			}

			// Create media_preview object (like YouTube oembed).
			$data['meta']['media_preview'] = array(
				'type'         => 'iframe_html',
				'provider'     => $enabled_provider,
				'html'         => $player_html,
				'image'        => $thumbnail_url,
				'video_id'     => $video_id,
				'status'       => 'pending', // Will be updated to 'ready' by webhook.
				'content_type' => 'video',
			);

			// Remove shortcode from message and message_rendered.
			$data['message']          = preg_replace( $pattern, '', $message );
			$data['message_rendered'] = preg_replace( $pattern, '', $message_rendered );

			// Clean up extra whitespace.
			$data['message']          = trim( $data['message'] );
			$data['message_rendered'] = trim( $data['message_rendered'] );

			error_log( '[FCHub Stream] process_shortcodes_before_save() - Created media_preview and removed shortcode' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return $data;
	}

	/**
	 * Process comment media after creation
	 *
	 * Handles video shortcodes in comment media by creating media_preview meta.
	 * Uses raw request body to access media object before FluentCommunity processing.
	 *
	 * @param object $comment Comment model instance.
	 * @param object $feed Feed model instance (unused).
	 * @return void
	 */
	public function process_comment_media_after_create( $comment, $feed ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Get raw request body to check for media object.
		$raw_body     = file_get_contents( 'php://input' );
		$request_data = json_decode( $raw_body, true );

		error_log( '[FCHub Stream] process_comment_media_after_create() - comment ID: ' . $comment->id . ', request keys: ' . implode( ', ', array_keys( $request_data ?? array() ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		if ( isset( $request_data['media']['html'] ) && strpos( $request_data['media']['html'], '[fchub_stream:' ) !== false ) {
			error_log( '[FCHub Stream] process_comment_media_after_create() - Found video in media' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			$shortcode = $request_data['media']['html'];
			$pattern   = '/\[fchub_stream:([a-zA-Z0-9_-]+)(?:\s+provider="(cloudflare_stream|bunny_stream)")?\]/';

			if ( preg_match( $pattern, $shortcode, $matches ) ) {
				$video_id = $matches[1];
				$provider = $matches[2] ?? null;

				// Get status from media object (sent from frontend after upload).
				// Default to 'pending' if not provided.
				$status = $request_data['media']['status'] ?? 'pending';

				// Get customer_subdomain from frontend (for Cloudflare encoding overlay).
				$customer_subdomain = $request_data['media']['customer_subdomain'] ?? '';

				error_log( '[FCHub Stream] process_comment_media_after_create() - Video ID: ' . $video_id . ', Provider: ' . ( $provider ?? 'auto' ) . ', Status: ' . $status . ', Customer subdomain: ' . $customer_subdomain ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

				// Get player HTML with actual status from frontend.
				// Pass customer_subdomain as 4th parameter for encoding overlay.
				$player_html = $this->player_renderer->get_player_html( $video_id, $provider, $status, $customer_subdomain );

				// Get thumbnail.
				$thumbnail_url    = $request_data['media']['image'] ?? '';
				$enabled_provider = $provider ?? StreamConfigService::get_enabled_provider();

				// Get current meta (use model accessor).
				$meta = $comment->meta ?? array();

				// Note: player_html already includes wrapper div from VideoPlayerRenderer.
				// No need to wrap again - just use as-is.

				// Create media_preview for comment.
				$meta['media_preview'] = array(
					'type'         => 'iframe_html',
					'provider'     => $enabled_provider,
					'html'         => $player_html,
					'image'        => $thumbnail_url,
					'video_id'     => $video_id,
					'status'       => $status, // Use status from frontend (pending/ready).
					'content_type' => 'video',
				);

				// Use Eloquent model to update (proper way).
				$comment->meta = $meta;
				$saved         = $comment->save();

				if ( $saved ) {
					error_log( '[FCHub Stream] process_comment_media_after_create() - Comment meta updated successfully' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				} else {
					error_log( '[FCHub Stream] process_comment_media_after_create() - Failed to save comment' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
		}
	}

	/**
	 * Process shortcodes in single feed API response
	 *
	 * Validates video existence and replaces shortcodes with player HTML.
	 * Only processes feeds endpoint, skips chat and other endpoints.
	 *
	 * @param array $data API response data.
	 * @param array $with Additional data requested (unused).
	 * @return array Modified API response.
	 */
	public function process_shortcodes_in_response( $data, $with ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Only process if data is an array and has feed structure.
		if ( ! is_array( $data ) ) {
			return $data;
		}

		// CRITICAL: Only process feeds endpoint - skip chat and other endpoints.
		// Chat returns ['messages' => ...], not ['feed' => ...].
		if ( ! isset( $data['feed'] ) ) {
			return $data;
		}

		// Process feed if it exists.
		if ( isset( $data['feed'] ) && is_array( $data['feed'] ) ) {
			// Validate video exists if meta has video_id.
			if ( isset( $data['feed']['meta']['media_preview']['video_id'] ) ) {
				$video_id = $data['feed']['meta']['media_preview']['video_id'];
				$provider = $data['feed']['meta']['media_preview']['provider'] ?? null;
				$status   = $data['feed']['meta']['media_preview']['status'] ?? null;
				$html     = $data['feed']['meta']['media_preview']['html'] ?? '';

				// Check if video exists in provider (only if provider is our stream provider).
				if ( in_array( $provider, array( 'cloudflare_stream', 'bunny_stream' ), true ) ) {
					// If status is 'ready', ensure HTML is updated (not encoding overlay).
					if ( 'ready' === $status ) {
						// Check if HTML still contains encoding overlay (old HTML).
						if ( strpos( $html, 'fchub-stream-encoding' ) !== false || strpos( $html, 'Encoding video...' ) !== false ) {
							// Regenerate HTML with ready status (no encoding overlay).
							$player_html = $this->player_renderer->get_player_html( $video_id, $provider, 'ready' );
							// Note: player_html already includes wrapper - no need to wrap again.
							$data['feed']['meta']['media_preview']['html'] = $player_html;
							error_log( '[FCHub Stream] Updated media_preview HTML for ready video in post ID: ' . ( $data['feed']['id'] ?? 'unknown' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						}
					} elseif ( strpos( $html, '<iframe' ) !== false && strpos( $html, 'fchub-stream-encoding' ) === false ) {
						// If status is not 'ready', ensure HTML shows encoding overlay (not iframe).
						// HTML contains iframe but status is pending - regenerate with encoding overlay.
						$player_html = $this->player_renderer->get_player_html( $video_id, $provider, 'pending' );
						// Note: player_html already includes wrapper - no need to wrap again.
						$data['feed']['meta']['media_preview']['html'] = $player_html;
						error_log( '[FCHub Stream] Updated media_preview HTML for pending video (removed iframe) in post ID: ' . ( $data['feed']['id'] ?? 'unknown' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

						// Note: We don't check video_exists() for pending videos because:
						// 1. Video may exist but not be ready yet (encoding in progress).
						// 2. API may return 404/500 temporarily during encoding.
						// 3. We trust the status field - if it's pending, show encoding overlay.
					}
				}
			}

			// Only process if feed exists and has message_rendered.
			if ( isset( $data['feed']['message_rendered'] ) && is_string( $data['feed']['message_rendered'] ) ) {
				if ( strpos( $data['feed']['message_rendered'], '[fchub_stream:' ) !== false ) {
					$data['feed']['message_rendered'] = $this->player_renderer->replace_shortcodes_with_player( $data['feed']['message_rendered'] );
				}
			}
		}

		return $data;
	}

	/**
	 * Process shortcodes in feeds list API response
	 *
	 * Handles both Collection and array responses from FluentCommunity.
	 * Validates video existence and replaces shortcodes with player HTML.
	 * Only processes feeds endpoint, skips chat and other endpoints.
	 *
	 * @param array $data API response data.
	 * @param array $with Additional data requested (unused).
	 * @return array Modified API response.
	 */
	public function process_shortcodes_in_feeds( $data, $with ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Early return if data is not an array or is null.
		if ( ! is_array( $data ) || is_null( $data ) ) {
			return $data;
		}

		// CRITICAL: Only process feeds endpoint structure - skip chat and other endpoints.
		// Chat returns ['messages' => ...], not ['feeds' => ...].
		if ( ! isset( $data['feeds'] ) ) {
			// This is not a feeds endpoint (might be activities, chat, etc.) - return unchanged.
			return $data;
		}

		// Wrap in try-catch to prevent any errors from breaking the API response.
		try {
			// Preserve all original keys (sticky, execution_time, last_fetched_timestamp, etc.)
			// Only modify feeds.data if it exists and is accessible.
			if ( isset( $data['feeds']['data'] ) ) {
				$feeds_data = $data['feeds']['data'];

				// CRITICAL FIX: Only modify Collection if there are shortcodes to process.
				// Don't create new Collection instance unless necessary to preserve all properties.
				if ( is_object( $feeds_data ) && method_exists( $feeds_data, 'map' ) && method_exists( $feeds_data, 'toArray' ) ) {
					// It's a Collection - first check if any feed has shortcode WITHOUT calling toArray().
					// Using toArray() might modify Collection's internal state.
					$has_shortcode = false;

					// Use each() to iterate without converting to array.
					$feeds_data->each(
						function ( $feed ) use ( &$has_shortcode ) {
							if ( $has_shortcode ) {
									return; // Already found shortcode, skip rest.
							}

							$message_rendered = null;
							if ( is_object( $feed ) ) {
								$message_rendered = $feed->message_rendered ?? null;
							} elseif ( is_array( $feed ) ) {
								$message_rendered = $feed['message_rendered'] ?? null;
							}

							if ( $message_rendered && is_string( $message_rendered ) && strpos( $message_rendered, '[fchub_stream:' ) !== false ) {
								$has_shortcode = true;
							}
						}
					);

					// Process Collection - validate videos and process shortcodes.
					$data['feeds']['data'] = $feeds_data->map(
						function ( $feed ) {
							// Convert to array for easier manipulation (FluentCommunity returns Collection of models).
							$feed_array = is_object( $feed ) ? $feed->toArray() : $feed;
							$feed_id    = $feed_array['id'] ?? 'unknown';

							// Validate video exists if meta has video_id.
							if ( isset( $feed_array['meta']['media_preview']['video_id'] ) ) {
								$video_id = $feed_array['meta']['media_preview']['video_id'];
								$provider = $feed_array['meta']['media_preview']['provider'] ?? null;
								$status   = $feed_array['meta']['media_preview']['status'] ?? null;
								$html     = $feed_array['meta']['media_preview']['html'] ?? '';

								// Check if video exists in provider (only if provider is our stream provider).
								if ( in_array( $provider, array( 'cloudflare_stream', 'bunny_stream' ), true ) ) {
									// If status is 'ready', ensure HTML is updated (not encoding overlay).
									if ( 'ready' === $status ) {
										// Check if HTML still contains encoding overlay (old HTML).
										if ( strpos( $html, 'fchub-stream-encoding' ) !== false || strpos( $html, 'Encoding video...' ) !== false ) {
											// Regenerate HTML with ready status (no encoding overlay).
											$player_html = $this->player_renderer->get_player_html( $video_id, $provider, 'ready' );
											// Note: player_html already includes wrapper - no need to wrap again.
											$feed_array['meta']['media_preview']['html'] = $player_html;
											error_log( '[FCHub Stream] Updated media_preview HTML for ready video in post ID: ' . $feed_id ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
										}
									} elseif ( strpos( $html, '<iframe' ) !== false && strpos( $html, 'fchub-stream-encoding' ) === false ) {
										// If status is not 'ready', ensure HTML shows encoding overlay (not iframe).
										// HTML contains iframe but status is pending - regenerate with encoding overlay.
										$player_html = $this->player_renderer->get_player_html( $video_id, $provider, 'pending' );
										// Note: player_html already includes wrapper - no need to wrap again.
										$feed_array['meta']['media_preview']['html'] = $player_html;
										error_log( '[FCHub Stream] Updated media_preview HTML for pending video (removed iframe) in post ID: ' . $feed_id ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

										// Note: We don't check video_exists() for pending videos because:
										// 1. Video may exist but not be ready yet (encoding in progress).
										// 2. API may return 404/500 temporarily during encoding.
										// 3. We trust the status field - if it's pending, show encoding overlay.
									}
								}
							}

							// Process shortcodes in message_rendered.
							if ( isset( $feed_array['message_rendered'] ) && is_string( $feed_array['message_rendered'] ) ) {
								if ( strpos( $feed_array['message_rendered'], '[fchub_stream:' ) !== false ) {
									$feed_array['message_rendered'] = $this->player_renderer->replace_shortcodes_with_player( $feed_array['message_rendered'] );
								}
							}

							// Return as array (FluentCommunity will handle conversion if needed).
							return $feed_array;
						}
					);
					// If no shortcode found, leave Collection unchanged (don't create new instance).
				} elseif ( is_array( $feeds_data ) ) {
					// It's already an array - process normally.
					$modified = false;
					foreach ( $feeds_data as $key => $feed ) {
						// Validate video exists if meta has video_id.
						if ( isset( $feed['meta']['media_preview']['video_id'] ) ) {
							$video_id = $feed['meta']['media_preview']['video_id'];
							$provider = $feed['meta']['media_preview']['provider'] ?? null;
							$status   = $feed['meta']['media_preview']['status'] ?? null;
							$html     = $feed['meta']['media_preview']['html'] ?? '';

							// Check if video exists in provider (only if provider is our stream provider).
							if ( in_array( $provider, array( 'cloudflare_stream', 'bunny_stream' ), true ) ) {
								// If status is 'ready', ensure HTML is updated (not encoding overlay).
								if ( 'ready' === $status ) {
									// Check if HTML still contains encoding overlay (old HTML).
									if ( strpos( $html, 'fchub-stream-encoding' ) !== false || strpos( $html, 'Encoding video...' ) !== false ) {
										// Regenerate HTML with ready status (no encoding overlay).
										$player_html = $this->player_renderer->get_player_html( $video_id, $provider, 'ready' );
										// Note: player_html already includes wrapper - no need to wrap again.
										$feeds_data[ $key ]['meta']['media_preview']['html'] = $player_html;
										$feed_id = $feed['id'] ?? 'unknown';
										error_log( '[FCHub Stream] Updated media_preview HTML for ready video in post ID: ' . $feed_id ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
										$modified = true;
									}
								} elseif ( strpos( $html, '<iframe' ) !== false && strpos( $html, 'fchub-stream-encoding' ) === false ) {
									// If status is not 'ready', ensure HTML shows encoding overlay (not iframe).
									// HTML contains iframe but status is pending - regenerate with encoding overlay.
									$player_html = $this->player_renderer->get_player_html( $video_id, $provider, 'pending' );
									// Note: player_html already includes wrapper - no need to wrap again.
									$feeds_data[ $key ]['meta']['media_preview']['html'] = $player_html;
									$feed_id = $feed['id'] ?? 'unknown';
									error_log( '[FCHub Stream] Updated media_preview HTML for pending video (removed iframe) in post ID: ' . $feed_id ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
									$modified = true;

									// Note: We don't check video_exists() for pending videos because:
									// 1. Video may exist but not be ready yet (encoding in progress).
									// 2. API may return 404/500 temporarily during encoding.
									// 3. We trust the status field - if it's pending, show encoding overlay.
								}
							}
						}

						if ( isset( $feed['message_rendered'] ) && is_string( $feed['message_rendered'] ) ) {
							if ( strpos( $feed['message_rendered'], '[fchub_stream:' ) !== false ) {
								$feeds_data[ $key ]['message_rendered'] = $this->player_renderer->replace_shortcodes_with_player( $feed['message_rendered'] );
								$modified                               = true;
							}
						}
					}

					if ( $modified ) {
						$data['feeds']['data'] = $feeds_data;
					}
				}
				// If unknown type, don't modify - return unchanged.
			}

			// Note: We don't add missing keys like 'sticky', 'activities', etc.
			// because FluentCommunity FeedsController already sets them appropriately.
			// Adding them here could cause issues if they're not expected.
		} catch ( \Exception $e ) {
			// Log error but return original data unchanged to prevent breaking the API.
			error_log( 'FCHub Stream: Error processing feeds API response: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			SentryService::capture_exception( $e );
			// Return original data unchanged.
		}

		return $data;
	}
}
