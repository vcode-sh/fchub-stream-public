<?php
/**
 * PHPUnit bootstrap file
 *
 * @package FCHub\License\Tests
 */

// Load Composer autoloader.
require_once dirname( __DIR__ ) . '/../../vendor/autoload.php';

// Define WordPress constants for testing.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'AUTH_KEY' ) ) {
	define( 'AUTH_KEY', 'test-auth-key' );
}

if ( ! defined( 'AUTH_SALT' ) ) {
	define( 'AUTH_SALT', 'test-auth-salt' );
}

// Mock WordPress functions for testing (if not already defined).
if ( ! function_exists( 'get_site_url' ) ) {
	function get_site_url() {
		return 'https://example.com';
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $wp_test_options;
		return $wp_test_options[ $option ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		global $wp_test_options;
		$wp_test_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		global $wp_test_options;
		unset( $wp_test_options[ $option ] );
		return true;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		if ( 'mysql' === $type ) {
			return gmdate( 'Y-m-d H:i:s' );
		}
		return time();
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $errors = array();
		private $error_data = array();

		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( empty( $code ) ) {
				return;
			}
			$this->errors[ $code ]      = array( $message );
			$this->error_data[ $code ] = $data;
		}

		public function get_error_code() {
			return key( $this->errors );
		}

		public function get_error_message( $code = '' ) {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			return $this->errors[ $code ][0] ?? '';
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( $url, $args = array() ) {
		// Mock implementation - returns success by default.
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode(
				array(
					'success' => true,
					'license' => array(
						'plan'       => 'pro',
						'expires_at' => '2025-12-31',
						'features'   => array( 'video_upload' => true ),
					),
				)
			),
		);
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return $response['body'] ?? '';
	}
}

// Initialize global test options storage.
global $wp_test_options;
$wp_test_options = array();
