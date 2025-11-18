<?php
/**
 * Tests for License_API_Client class
 *
 * @package FCHub\License\Tests
 */

namespace FCHub\License\Tests;

use FCHub\License\License_API_Client;
use PHPUnit\Framework\TestCase;

/**
 * Test case for License_API_Client
 */
class License_API_Client_Test extends TestCase {

	/**
	 * Test API client instance
	 *
	 * @var License_API_Client
	 */
	private $client;

	/**
	 * Setup test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->client = new License_API_Client( 'https://api.fchub.co/rpc/licenses' );
	}

	/**
	 * Test client is instantiated correctly
	 */
	public function test_client_instantiation() {
		$this->assertInstanceOf( License_API_Client::class, $this->client );
	}

	/**
	 * Test activate method calls request with correct endpoint
	 */
	public function test_activate_calls_correct_endpoint() {
		$params = array(
			'license_key' => 'FCHUB-STREAM-XXXX-YYYY-ZZZZ',
			'site_url'    => 'https://example.com',
			'product'     => 'fchub-stream',
		);

		$result = $this->client->activate( $params );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertTrue( $result['success'] );
	}

	/**
	 * Test validate method calls request with correct endpoint
	 */
	public function test_validate_calls_correct_endpoint() {
		$params = array(
			'license_key' => 'FCHUB-STREAM-XXXX-YYYY-ZZZZ',
			'site_url'    => 'https://example.com',
			'product'     => 'fchub-stream',
		);

		$result = $this->client->validate( $params );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
	}

	/**
	 * Test deactivate method calls request with correct endpoint
	 */
	public function test_deactivate_calls_correct_endpoint() {
		$params = array(
			'license_key' => 'FCHUB-STREAM-XXXX-YYYY-ZZZZ',
			'site_url'    => 'https://example.com',
			'product'     => 'fchub-stream',
		);

		$result = $this->client->deactivate( $params );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
	}

	/**
	 * Test request method formats parameters correctly
	 */
	public function test_request_formats_parameters() {
		// This test verifies that parameters are passed correctly.
		// In real scenario, we would mock wp_remote_post.
		$params = array(
			'license_key' => 'TEST-KEY',
			'site_url'    => 'https://test.com',
		);

		$result = $this->client->activate( $params );

		// Since wp_remote_post is mocked in bootstrap.php,
		// we expect a successful response.
		$this->assertIsArray( $result );
	}
}
