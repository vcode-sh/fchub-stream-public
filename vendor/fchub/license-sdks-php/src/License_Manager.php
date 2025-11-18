<?php
/**
 * Universal License Manager (Abstract Base Class)
 *
 * Provides license activation, validation, and management for FCHub products.
 * Each product extends this class and implements get_product_slug().
 *
 * @package FCHub\License
 * @since 1.0.0
 */

namespace FCHub\License;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class License_Manager {

	/**
	 * FCHub License API base URL
	 *
	 * Production: https://api.fchub.co/rpc
	 * oRPC endpoint format: {API_BASE}/licenses.{procedure}
	 */
	const API_BASE = 'https://api.fchub.co/rpc';

	/**
	 * Storage instance
	 *
	 * @var License_Storage
	 */
	protected $storage;

	/**
	 * API client instance
	 *
	 * @var License_API_Client
	 */
	protected $api;

	/**
	 * Validator instance
	 *
	 * @var License_Validator
	 */
	protected $validator;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->storage = new License_Storage( $this->get_product_slug() );
		$this->api     = new License_API_Client( self::API_BASE );
		$this->validator = new License_Validator( $this );
	}

	/**
	 * Get product slug (must be implemented by child class)
	 *
	 * @return string Product slug (e.g., 'fchub-companion', 'fchub-stream')
	 */
	abstract protected function get_product_slug(): string;

	/**
	 * Get product slug (public accessor for validator)
	 *
	 * @return string Product slug
	 */
	public function get_product_slug_public(): string {
		return $this->get_product_slug();
	}

	/**
	 * Activate license
	 *
	 * @param string $license_key License key
	 * @param string $site_url    Site URL (default: current site)
	 * @return array|WP_Error Response or error
	 */
	public function activate_license( $license_key, $site_url = null ) {
		if ( empty( $site_url ) ) {
			$site_url = get_site_url();
		}

		// Validate format
		if ( ! $this->validate_license_key_format( $license_key ) ) {
			return new \WP_Error(
				'invalid_license_key',
				__( 'Invalid license key format.', 'fchub-license' )
			);
		}

		// Call API
		$response = $this->api->activate(
			array(
				'license_key' => $license_key,
				'site_url'    => $site_url,
				'product'     => $this->get_product_slug(),  // ✅ Send product
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Store credentials
		// Companion: fields directly in license (max_sites, max_connections, etc.)
		// Other products: features object
		$license_data = $response['license'] ?? array();
		$features = isset( $license_data['features'] )
			? $license_data['features']  // Other products (Stream, etc.)
			: $license_data;              // Companion (all fields are features)

		$this->storage->save(
			array(
				'key'          => $license_key,
				'plan'         => $license_data['plan'] ?? '',
				'expires_at'   => $license_data['expires_at'] ?? '',
				'features'     => $features,
				'activated_at' => current_time( 'mysql' ),
			)
		);

		return $response;
	}

	/**
	 * Validate license
	 *
	 * @return array|WP_Error Response or error
	 */
	public function validate_license() {
		$license_data = $this->storage->get();

		if ( ! $license_data || empty( $license_data['key'] ) ) {
			return new \WP_Error( 'no_license', __( 'License not configured', 'fchub-license' ) );
		}

		$response = $this->api->validate(
			array(
				'license_key' => $license_data['key'],
				'site_url'    => get_site_url(),
				'product'     => $this->get_product_slug(),  // ✅ Send product
			)
		);

		if ( is_wp_error( $response ) ) {
			// Grace period: Keep working if validation fails temporarily
			$last_validated = $license_data['last_validated_at'] ?? 0;
			$grace_days     = 3; // ✅ 3 days grace period

			if ( ( time() - $last_validated ) < ( $grace_days * DAY_IN_SECONDS ) ) {
				return $license_data; // Use cached data
			}

			return $response; // Grace period expired
		}

		// Update storage
		// Companion: fields directly in license (max_sites, max_connections, etc.)
		// Other products: features object
		$response_license = $response['license'] ?? array();
		$features = isset( $response_license['features'] )
			? $response_license['features']  // Other products (Stream, etc.)
			: $response_license;              // Companion (all fields are features)

		$license_data['last_validated_at'] = time();
		$license_data['features']          = $features;
		$this->storage->save( $license_data );

		return $response;
	}

	/**
	 * Deactivate license
	 *
	 * @return array|WP_Error Response or error
	 */
	public function deactivate_license() {
		$license_data = $this->storage->get();

		if ( ! $license_data || empty( $license_data['key'] ) ) {
			return new \WP_Error( 'no_license', __( 'License not configured', 'fchub-license' ) );
		}

		$response = $this->api->deactivate(
			array(
				'license_key' => $license_data['key'],
				'site_url'    => get_site_url(),
				'product'     => $this->get_product_slug(),  // ✅ Send product
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$this->storage->clear();
		}

		return $response;
	}

	/**
	 * Check if license is active
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		$license_data = $this->storage->get();
		return ! empty( $license_data['key'] );
	}

	/**
	 * Get license features
	 *
	 * @return array Features array
	 */
	public function get_features(): array {
		$license_data = $this->storage->get();
		return $license_data['features'] ?? array();
	}

	/**
	 * Validate license key format
	 *
	 * @param string $key License key
	 * @return bool
	 */
	protected function validate_license_key_format( $key ): bool {
		return (bool) preg_match( '/^FCHUB-[A-Z]+-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $key );
	}

	/**
	 * Get validator instance
	 *
	 * @return License_Validator
	 */
	public function get_validator(): License_Validator {
		return $this->validator;
	}

	/**
	 * Track feature usage (increments counter, auto-validates if needed)
	 *
	 * Call this method whenever a license-protected feature is used.
	 *
	 * @return void
	 */
	public function track_usage(): void {
		$this->validator->increment_usage();
	}
}
