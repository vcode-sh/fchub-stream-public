<?php
/**
 * Test script for License SDK availability
 * 
 * Run this from WordPress root or plugin directory to test SDK loading
 * Usage: php test-license-sdk.php
 */

// Simulate WordPress environment
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// WordPress constants
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 24 * 60 * 60 );
}

// Helper function (WordPress function)
if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return trailingslashit( dirname( $file ) );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}

// Try to detect plugin directory
$plugin_dir = __DIR__;
if ( file_exists( __DIR__ . '/fchub-stream.php' ) ) {
	// We're in plugin root
	define( 'FCHUB_STREAM_DIR', plugin_dir_path( __DIR__ . '/fchub-stream.php' ) );
	define( 'FCHUB_STREAM_FILE', __DIR__ . '/fchub-stream.php' );
} else {
	// Try WordPress plugins directory
	$wp_plugins_dir = dirname( dirname( __DIR__ ) ) . '/wp-content/plugins/fchub-stream';
	if ( file_exists( $wp_plugins_dir . '/fchub-stream.php' ) ) {
		define( 'FCHUB_STREAM_DIR', plugin_dir_path( $wp_plugins_dir . '/fchub-stream.php' ) );
		define( 'FCHUB_STREAM_FILE', $wp_plugins_dir . '/fchub-stream.php' );
	} else {
		define( 'FCHUB_STREAM_DIR', __DIR__ . '/' );
		define( 'FCHUB_STREAM_FILE', __DIR__ . '/fchub-stream.php' );
	}
}

echo "=== FCHub Stream License SDK Test ===\n\n";
echo "Plugin Directory: " . FCHUB_STREAM_DIR . "\n";
echo "Plugin File: " . FCHUB_STREAM_FILE . "\n\n";

// Check autoloader
$autoload_path = rtrim( FCHUB_STREAM_DIR, '/' ) . '/vendor/autoload.php';
echo "Autoload Path: " . $autoload_path . "\n";
echo "Autoload Exists: " . ( file_exists( $autoload_path ) ? 'YES' : 'NO' ) . "\n\n";

if ( ! file_exists( $autoload_path ) ) {
	echo "❌ ERROR: Autoloader not found!\n";
	echo "Run: composer install\n";
	exit( 1 );
}

// Load autoloader
require_once $autoload_path;
echo "✓ Autoloader loaded\n\n";

// Check SDK classes
$classes = array(
	'FCHub\License\License_Manager',
	'FCHub\License\License_Storage',
	'FCHub\License\License_API_Client',
	'FCHub\License\License_Validator',
	'FCHubStream\App\Services\StreamLicenseManager',
);

echo "=== Class Availability ===\n";
foreach ( $classes as $class ) {
	$exists = class_exists( $class );
	echo ( $exists ? '✓' : '❌' ) . " $class: " . ( $exists ? 'OK' : 'NOT FOUND' ) . "\n";
}

// Check SDK directory
$sdk_path = rtrim( FCHUB_STREAM_DIR, '/' ) . '/vendor/fchub/license-sdks-php';
echo "\n=== SDK Directory ===\n";
echo "SDK Path: " . $sdk_path . "\n";
echo "SDK Exists: " . ( file_exists( $sdk_path ) || is_link( $sdk_path ) ? 'YES' : 'NO' ) . "\n";

if ( file_exists( $sdk_path ) || is_link( $sdk_path ) ) {
	$real_path = realpath( $sdk_path );
	echo "SDK Real Path: " . ( $real_path ?: 'NOT RESOLVABLE' ) . "\n";
	
	if ( is_link( $sdk_path ) ) {
		echo "SDK Type: SYMLINK\n";
		echo "Symlink Target: " . readlink( $sdk_path ) . "\n";
	} else {
		echo "SDK Type: DIRECTORY\n";
	}
}

// Try to instantiate StreamLicenseManager
echo "\n=== Instantiation Test ===\n";
if ( class_exists( 'FCHubStream\App\Services\StreamLicenseManager' ) ) {
	try {
		$license = new \FCHubStream\App\Services\StreamLicenseManager();
		echo "✓ StreamLicenseManager instantiated successfully\n";
		echo "  Is Active: " . ( $license->is_active() ? 'YES' : 'NO' ) . "\n";
	} catch ( \Exception $e ) {
		echo "❌ ERROR instantiating StreamLicenseManager: " . $e->getMessage() . "\n";
		echo "  File: " . $e->getFile() . "\n";
		echo "  Line: " . $e->getLine() . "\n";
	}
} else {
	echo "❌ StreamLicenseManager class not found\n";
}

echo "\n=== Test Complete ===\n";

