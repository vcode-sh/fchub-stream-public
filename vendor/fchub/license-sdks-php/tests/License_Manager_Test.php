<?php
/**
 * Tests for License_Manager class
 *
 * @package FCHub\License\Tests
 */

namespace FCHub\License\Tests;

use FCHub\License\License_Manager;
use PHPUnit\Framework\TestCase;

/**
 * Mock implementation of License_Manager for testing
 */
class Mock_License_Manager extends License_Manager {
	protected function get_product_slug(): string {
		return 'fchub-stream';
	}
}

/**
 * Test case for License_Manager
 */
class License_Manager_Test extends TestCase {

	/**
	 * Test license manager instance
	 *
	 * @var Mock_License_Manager
	 */
	private $manager;

	/**
	 * Setup test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		global $wp_test_options;
		$wp_test_options = array(); // Reset options.
		$this->manager   = new Mock_License_Manager();
	}

	/**
	 * Test manager is instantiated correctly
	 */
	public function test_manager_instantiation() {
		$this->assertInstanceOf( License_Manager::class, $this->manager );
	}

	/**
	 * Test activate_license validates key format
	 */
	public function test_activate_license_validates_format() {
		$result = $this->manager->activate_license( 'INVALID-KEY' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_license_key', $result->get_error_code() );
	}

	/**
	 * Test activate_license with valid key
	 */
	public function test_activate_license_with_valid_key() {
		$result = $this->manager->activate_license(
			'FCHUB-STREAM-XXXX-YYYY-ZZZZ',
			'https://example.com'
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertTrue( $result['success'] );
	}

	/**
	 * Test activate_license uses current site if no URL provided
	 */
	public function test_activate_license_uses_current_site() {
		$result = $this->manager->activate_license( 'FCHUB-STREAM-XXXX-YYYY-ZZZZ' );

		$this->assertIsArray( $result );
		// get_site_url() is mocked to return https://example.com.
		$this->assertTrue( $result['success'] );
	}

	/**
	 * Test is_active returns false when no license
	 */
	public function test_is_active_returns_false_when_empty() {
		$this->assertFalse( $this->manager->is_active() );
	}

	/**
	 * Test is_active returns true after activation
	 */
	public function test_is_active_returns_true_after_activation() {
		$this->manager->activate_license( 'FCHUB-STREAM-XXXX-YYYY-ZZZZ' );
		$this->assertTrue( $this->manager->is_active() );
	}

	/**
	 * Test get_features returns empty array when no license
	 */
	public function test_get_features_returns_empty_when_no_license() {
		$features = $this->manager->get_features();
		$this->assertIsArray( $features );
		$this->assertEmpty( $features );
	}

	/**
	 * Test get_features returns features after activation
	 */
	public function test_get_features_returns_features_after_activation() {
		$this->manager->activate_license( 'FCHUB-STREAM-XXXX-YYYY-ZZZZ' );
		$features = $this->manager->get_features();

		$this->assertIsArray( $features );
		$this->assertArrayHasKey( 'video_upload', $features );
		$this->assertTrue( $features['video_upload'] );
	}

	/**
	 * Test validate_license_key_format accepts valid keys
	 */
	public function test_validate_license_key_format_valid() {
		$reflection = new \ReflectionClass( $this->manager );
		$method     = $reflection->getMethod( 'validate_license_key_format' );
		$method->setAccessible( true );

		$valid_keys = array(
			'FCHUB-STREAM-AAAA-BBBB-CCCC',
			'FCHUB-PRO-XXXX-YYYY-ZZZZ',
			'FCHUB-BUSINESS-1234-5678-9ABC',
		);

		foreach ( $valid_keys as $key ) {
			$result = $method->invoke( $this->manager, $key );
			$this->assertTrue( $result, "Key should be valid: $key" );
		}
	}

	/**
	 * Test validate_license_key_format rejects invalid keys
	 */
	public function test_validate_license_key_format_invalid() {
		$reflection = new \ReflectionClass( $this->manager );
		$method     = $reflection->getMethod( 'validate_license_key_format' );
		$method->setAccessible( true );

		$invalid_keys = array(
			'INVALID-KEY',
			'FCHUB-STREAM',
			'FCHUB-STREAM-XXX-YYY-ZZZ', // Too short.
			'fchub-stream-xxxx-yyyy-zzzz', // Lowercase.
			'FCHUB-STREAM-XXXX-YYYY',      // Missing section.
		);

		foreach ( $invalid_keys as $key ) {
			$result = $method->invoke( $this->manager, $key );
			$this->assertFalse( $result, "Key should be invalid: $key" );
		}
	}

	/**
	 * Test validate_license returns error when no license configured
	 */
	public function test_validate_license_no_license() {
		$result = $this->manager->validate_license();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'no_license', $result->get_error_code() );
	}

	/**
	 * Test deactivate_license returns error when no license configured
	 */
	public function test_deactivate_license_no_license() {
		$result = $this->manager->deactivate_license();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'no_license', $result->get_error_code() );
	}

	/**
	 * Test deactivate_license clears stored data
	 */
	public function test_deactivate_license_clears_data() {
		$this->manager->activate_license( 'FCHUB-STREAM-XXXX-YYYY-ZZZZ' );
		$this->assertTrue( $this->manager->is_active() );

		$this->manager->deactivate_license();
		$this->assertFalse( $this->manager->is_active() );
	}

	/**
	 * Test grace period logic
	 */
	public function test_grace_period_logic() {
		// Activate first.
		$this->manager->activate_license( 'FCHUB-STREAM-XXXX-YYYY-ZZZZ' );

		// Validate to populate last_validated_at.
		$this->manager->validate_license();

		// Even if API fails (mocked), should work within grace period.
		$this->assertTrue( $this->manager->is_active() );
	}
}
