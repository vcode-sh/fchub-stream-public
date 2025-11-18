<?php
/**
 * License Validator
 *
 * Handles automatic license validation scheduling based on time and usage.
 * Validates license periodically to ensure it's still active and valid.
 *
 * @package FCHub\License
 * @since 1.0.0
 */

namespace FCHub\License;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class License_Validator {

	/**
	 * License manager instance
	 *
	 * @var License_Manager
	 */
	protected $manager;

	/**
	 * Product slug
	 *
	 * @var string
	 */
	protected $product_slug;

	/**
	 * Validation configuration
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * Constructor
	 *
	 * @param License_Manager $manager License manager instance
	 * @param array           $config  Optional configuration
	 */
	public function __construct( License_Manager $manager, array $config = array() ) {
		$this->manager     = $manager;
		$this->product_slug = $manager->get_product_slug_public();
		$this->config      = array_merge(
			array(
				'time_interval'    => DAY_IN_SECONDS,      // ✅ 1 day (was 7 days)
				'usage_threshold'  => 500,                  // ✅ 500 uses (was 1000)
				'grace_period'     => 3 * DAY_IN_SECONDS,   // ✅ 3 days grace period (was 30)
			),
			$config
		);
	}

	/**
	 * Check if validation should run
	 *
	 * @return bool True if validation should run
	 */
	public function should_validate(): bool {
		// Time-based: Check if enough time has passed
		$last_validation = $this->get_last_validation_time();
		if ( ( time() - $last_validation ) >= $this->config['time_interval'] ) {
			return true;
		}

		// Usage-based: Check if usage threshold reached
		$usage_count = $this->get_usage_count();
		if ( $usage_count >= $this->config['usage_threshold'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Validate license and update cache
	 *
	 * @return array|WP_Error Validation result
	 */
	public function validate_and_update() {
		$result = $this->manager->validate_license();

		if ( is_wp_error( $result ) ) {
			// Network error - check grace period
			if ( $this->is_in_grace_period() ) {
				// Still in grace period, return cached data
				return $this->get_cached_license_data();
			}
			// Grace period expired
			return $result;
		}

		// Success - update last validation time and reset counters
		$this->update_last_validation_time();
		$this->reset_usage_count();

		return $result;
	}

	/**
	 * Increment usage counter
	 *
	 * Call this method whenever license-protected feature is used.
	 * Automatically triggers validation if threshold reached.
	 *
	 * @return void
	 */
	public function increment_usage(): void {
		$count = $this->get_usage_count() + 1;
		$this->set_usage_count( $count );

		// Auto-validate if threshold reached
		if ( $this->should_validate() ) {
			$this->validate_and_update();
		}
	}

	/**
	 * Get last validation time
	 *
	 * @return int Unix timestamp
	 */
	protected function get_last_validation_time(): int {
		return (int) get_option( $this->get_option_name( 'last_validation' ), 0 );
	}

	/**
	 * Update last validation time
	 *
	 * @return void
	 */
	protected function update_last_validation_time(): void {
		update_option( $this->get_option_name( 'last_validation' ), time() );
	}

	/**
	 * Get usage count
	 *
	 * @return int Usage count
	 */
	protected function get_usage_count(): int {
		return (int) get_option( $this->get_option_name( 'usage_count' ), 0 );
	}

	/**
	 * Set usage count
	 *
	 * @param int $count Usage count
	 * @return void
	 */
	protected function set_usage_count( int $count ): void {
		update_option( $this->get_option_name( 'usage_count' ), $count );
	}

	/**
	 * Reset usage count
	 *
	 * @return void
	 */
	protected function reset_usage_count(): void {
		$this->set_usage_count( 0 );
	}

	/**
	 * Check if in grace period
	 *
	 * @return bool True if in grace period
	 */
	protected function is_in_grace_period(): bool {
		$last_validation = $this->get_last_validation_time();
		if ( $last_validation === 0 ) {
			return false; // Never validated, no grace period
		}

		$time_since_validation = time() - $last_validation;
		return $time_since_validation < $this->config['grace_period'];
	}

	/**
	 * Get cached license data
	 *
	 * @return array|null License data or null
	 */
	protected function get_cached_license_data() {
		$storage = new License_Storage( $this->product_slug );
		return $storage->get();
	}

	/**
	 * Get option name for this product
	 *
	 * @param string $suffix Option suffix
	 * @return string Option name
	 */
	protected function get_option_name( string $suffix ): string {
		return "fchub_{$this->product_slug}_validator_{$suffix}";
	}
}

