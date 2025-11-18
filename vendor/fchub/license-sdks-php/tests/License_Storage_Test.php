<?php
/**
 * Tests for License_Storage class
 *
 * @package FCHub\License\Tests
 */

namespace FCHub\License\Tests;

use FCHub\License\License_Storage;
use PHPUnit\Framework\TestCase;

/**
 * Test case for License_Storage
 */
class License_Storage_Test extends TestCase {

	/**
	 * Test storage instance
	 *
	 * @var License_Storage
	 */
	private $storage;

	/**
	 * Setup test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		global $wp_test_options;
		$wp_test_options = array(); // Reset options.
		$this->storage   = new License_Storage( 'fchub-stream' );
	}

	/**
	 * Test get_option_name returns correct option name
	 */
	public function test_option_name_format() {
		// Use reflection to test protected method.
		$reflection = new \ReflectionClass( $this->storage );
		$method     = $reflection->getMethod( 'get_option_name' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->storage );
		$this->assertEquals( 'fchub_fchub-stream_license', $result );
	}

	/**
	 * Test save method stores encrypted data
	 */
	public function test_save_stores_encrypted_data() {
		$data   = array(
			'key'        => 'FCHUB-STREAM-XXXX-YYYY-ZZZZ',
			'plan'       => 'pro',
			'expires_at' => '2025-12-31',
		);
		$result = $this->storage->save( $data );

		$this->assertTrue( $result );

		// Verify data is stored (encrypted).
		global $wp_test_options;
		$this->assertArrayHasKey( 'fchub_fchub-stream_license', $wp_test_options );
		$this->assertIsString( $wp_test_options['fchub_fchub-stream_license'] );
	}

	/**
	 * Test get method retrieves and decrypts data
	 */
	public function test_get_retrieves_decrypted_data() {
		$data = array(
			'key'        => 'FCHUB-STREAM-XXXX-YYYY-ZZZZ',
			'plan'       => 'pro',
			'expires_at' => '2025-12-31',
		);

		$this->storage->save( $data );
		$retrieved = $this->storage->get();

		$this->assertIsArray( $retrieved );
		$this->assertEquals( $data['key'], $retrieved['key'] );
		$this->assertEquals( $data['plan'], $retrieved['plan'] );
		$this->assertEquals( $data['expires_at'], $retrieved['expires_at'] );
	}

	/**
	 * Test get returns null when no data exists
	 */
	public function test_get_returns_null_when_empty() {
		$result = $this->storage->get();
		$this->assertNull( $result );
	}

	/**
	 * Test clear removes stored data
	 */
	public function test_clear_removes_data() {
		$data = array(
			'key'  => 'FCHUB-STREAM-XXXX-YYYY-ZZZZ',
			'plan' => 'pro',
		);

		$this->storage->save( $data );
		$this->assertNotNull( $this->storage->get() );

		$this->storage->clear();
		$this->assertNull( $this->storage->get() );
	}

	/**
	 * Test encryption and decryption cycle
	 */
	public function test_encryption_decryption_cycle() {
		$original_data = array(
			'key'      => 'FCHUB-STREAM-XXXX-YYYY-ZZZZ',
			'plan'     => 'pro',
			'features' => array(
				'video_upload'    => true,
				'max_video_size'  => 5,
				'analytics'       => true,
			),
		);

		$this->storage->save( $original_data );
		$retrieved_data = $this->storage->get();

		$this->assertEquals( $original_data, $retrieved_data );
	}

	/**
	 * Test product-specific storage isolation
	 */
	public function test_product_specific_storage() {
		$stream_storage    = new License_Storage( 'fchub-stream' );
		$companion_storage = new License_Storage( 'fchub-companion' );

		$stream_data    = array( 'key' => 'STREAM-KEY', 'plan' => 'pro' );
		$companion_data = array( 'key' => 'COMPANION-KEY', 'plan' => 'business' );

		$stream_storage->save( $stream_data );
		$companion_storage->save( $companion_data );

		// Verify each product has its own storage.
		$retrieved_stream    = $stream_storage->get();
		$retrieved_companion = $companion_storage->get();

		$this->assertEquals( 'STREAM-KEY', $retrieved_stream['key'] );
		$this->assertEquals( 'COMPANION-KEY', $retrieved_companion['key'] );
	}
}
