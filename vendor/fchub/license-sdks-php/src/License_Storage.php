<?php
/**
 * Encrypted License Storage
 *
 * Stores license data securely using AES-256-CBC encryption.
 *
 * @package FCHub\License
 * @since 1.0.0
 */

namespace FCHub\License;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class License_Storage {

	/**
	 * Product slug
	 *
	 * @var string
	 */
	protected $product_slug;

	/**
	 * Constructor
	 *
	 * @param string $product_slug Product identifier
	 */
	public function __construct( string $product_slug ) {
		$this->product_slug = $product_slug;
	}

	/**
	 * Get option name
	 *
	 * @return string
	 */
	protected function get_option_name(): string {
		return "fchub_{$this->product_slug}_license";
	}

	/**
	 * Save license data (encrypted)
	 *
	 * @param array $data License data
	 * @return bool Success
	 */
	public function save( array $data ): bool {
		$encrypted = $this->encrypt( wp_json_encode( $data ) );
		return update_option( $this->get_option_name(), $encrypted, false );
	}

	/**
	 * Get license data (decrypted)
	 *
	 * @return array|null License data or null
	 */
	public function get(): ?array {
		$encrypted = get_option( $this->get_option_name() );

		if ( ! $encrypted ) {
			return null;
		}

		$decrypted = $this->decrypt( $encrypted );

		if ( ! $decrypted ) {
			return null;
		}

		return json_decode( $decrypted, true );
	}

	/**
	 * Clear license data
	 *
	 * @return bool Success
	 */
	public function clear(): bool {
		return delete_option( $this->get_option_name() );
	}

	/**
	 * Encrypt data using AES-256-CBC
	 *
	 * @param string $data Data to encrypt
	 * @return string Encrypted data (base64)
	 */
	protected function encrypt( string $data ): string {
		$key       = $this->get_encryption_key();
		$iv        = openssl_random_pseudo_bytes( 16 );
		$encrypted = openssl_encrypt( $data, 'AES-256-CBC', $key, 0, $iv );
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt data
	 *
	 * @param string $data Encrypted data (base64)
	 * @return string|false Decrypted data or false
	 */
	protected function decrypt( string $data ) {
		try {
			$key       = $this->get_encryption_key();
			$decoded   = base64_decode( $data, true );
			
			if ( false === $decoded ) {
				error_log( '[FCHub License] Failed to decode base64 data for product: ' . $this->product_slug ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return false;
			}
			
			if ( strlen( $decoded ) < 16 ) {
				error_log( '[FCHub License] Encrypted data too short for product: ' . $this->product_slug ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return false;
			}
			
			$iv        = substr( $decoded, 0, 16 );
			$encrypted = substr( $decoded, 16 );
			
			if ( empty( $encrypted ) ) {
				error_log( '[FCHub License] No encrypted data found for product: ' . $this->product_slug ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return false;
			}
			
			$decrypted = openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );
			
			if ( false === $decrypted ) {
				error_log( '[FCHub License] Decryption failed for product: ' . $this->product_slug . '. Data may be corrupted or encrypted with different key.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			
			return $decrypted;
		} catch ( \Exception $e ) {
			error_log( '[FCHub License] Exception during decryption for product ' . $this->product_slug . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return false;
		}
	}

	/**
	 * Get encryption key
	 *
	 * @return string
	 */
	protected function get_encryption_key(): string {
		// Check if WordPress auth constants are defined
		if ( ! defined( 'AUTH_KEY' ) || ! defined( 'AUTH_SALT' ) ) {
			// Fallback: Use a default key if constants are not available
			// This should never happen in WordPress, but provides safety
			error_log( '[FCHub License] AUTH_KEY or AUTH_SALT not defined. Using fallback key.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return hash( 'sha256', 'fchub-license-default-key-salt', true );
		}
		
		return hash( 'sha256', AUTH_KEY . AUTH_SALT, true );
	}
}
