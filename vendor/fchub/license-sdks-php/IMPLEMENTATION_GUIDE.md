# FCHub License SDK - Implementation Guide

Complete guide for integrating FCHub License SDK into WordPress plugins.

**Version:** 1.0.0  
**Last Updated:** 2025-01-15

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [Installation](#installation)
3. [Basic Implementation](#basic-implementation)
4. [Advanced Usage](#advanced-usage)
5. [Admin Panel Integration](#admin-panel-integration)
6. [REST API Integration](#rest-api-integration)
7. [Feature Checking](#feature-checking)
8. [Error Handling](#error-handling)
9. [Best Practices](#best-practices)
10. [Examples](#examples)

---

## Quick Start

### 1. Install SDK

```bash
composer require fchub/license-sdks-php
```

### 2. Create License Manager

```php
<?php
namespace YourPlugin\License;

use FCHub\License\License_Manager;

class YourPlugin_License_Manager extends License_Manager {
    protected function get_product_slug(): string {
        return 'your-plugin-slug';
    }
}
```

### 3. Use in Plugin

```php
$license = new YourPlugin_License_Manager();

// Activate
$result = $license->activate_license('FCHUB-PRODUCT-XXXX-YYYY-ZZZZ');

// Check if active
if ($license->is_active()) {
    // Your licensed feature code
}
```

**That's it!** You now have full license management with encryption, validation, and grace period.

---

## Installation

### Requirements

- PHP 7.4+
- WordPress 5.0+
- Composer

### Install via Composer

```bash
cd /path/to/your-plugin
composer require fchub/license-sdks-php
```

### Autoload

The SDK uses PSR-4 autoloading. After installing via Composer, classes are automatically available:

```php
use FCHub\License\License_Manager;
use FCHub\License\License_Storage;
use FCHub\License\License_Validator;
```

---

## Basic Implementation

### Step 1: Create License Manager Class

Create a new file in your plugin (e.g., `includes/class-your-plugin-license-manager.php`):

```php
<?php
/**
 * Your Plugin License Manager
 *
 * @package YourPlugin
 * @since 1.0.0
 */

namespace YourPlugin\License;

use FCHub\License\License_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YourPlugin_License_Manager extends License_Manager {

	/**
	 * Get product slug
	 *
	 * This identifies your plugin to the FCHub License API.
	 * Must match the product slug configured in FCHub dashboard.
	 *
	 * @return string Product slug (e.g., 'fchub-stream', 'fchub-companion')
	 */
	protected function get_product_slug(): string {
		return 'your-plugin-slug'; // Change this to your product slug
	}
}
```

**Important:** The product slug must match exactly what's configured in your FCHub license system.

### Step 2: Initialize License Manager

In your main plugin file or a service class:

```php
<?php
namespace YourPlugin;

use YourPlugin\License\YourPlugin_License_Manager;

class YourPlugin {
	
	/**
	 * License manager instance
	 *
	 * @var YourPlugin_License_Manager
	 */
	protected $license_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->license_manager = new YourPlugin_License_Manager();
	}

	/**
	 * Get license manager
	 *
	 * @return YourPlugin_License_Manager
	 */
	public function get_license_manager() {
		return $this->license_manager;
	}
}
```

### Step 3: Basic Usage

```php
$license = new YourPlugin_License_Manager();

// Check if license is active
if ( $license->is_active() ) {
	// License is active - enable features
} else {
	// License not active - show upgrade notice
}

// Get license features
$features = $license->get_features();
```

---

## Advanced Usage

### Automatic Validation

The SDK automatically validates licenses in the background:

- **Time-based:** Validates every 24 hours
- **Usage-based:** Validates after 500 feature uses
- **Grace period:** 7 days offline tolerance

You don't need to manually call `validate_license()` - it happens automatically.

### Manual Validation

If you need to force validation:

```php
$license = new YourPlugin_License_Manager();
$result = $license->validate_license();

if ( is_wp_error( $result ) ) {
	// Handle error
	error_log( 'License validation failed: ' . $result->get_error_message() );
} else {
	// License is valid
	$license_data = $result['license'] ?? array();
}
```

### Track Feature Usage

Track when licensed features are used (triggers auto-validation):

```php
// Before using a licensed feature
$license->track_usage();

// Now use your feature
do_something_licensed();
```

---

## Admin Panel Integration

### License Settings Page

Create an admin settings page for license management:

```php
<?php
/**
 * License Settings Page
 */

namespace YourPlugin\Admin;

use YourPlugin\License\YourPlugin_License_Manager;

class License_Settings {

	/**
	 * License manager instance
	 *
	 * @var YourPlugin_License_Manager
	 */
	protected $license_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->license_manager = new YourPlugin_License_Manager();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	protected function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'handle_license_actions' ) );
	}

	/**
	 * Add admin menu page
	 */
	public function add_menu_page() {
		add_submenu_page(
			'your-plugin-menu',           // Parent menu slug
			__( 'License', 'your-plugin' ), // Page title
			__( 'License', 'your-plugin' ), // Menu title
			'manage_options',             // Capability
			'your-plugin-license',        // Menu slug
			array( $this, 'render_page' )  // Callback
		);
	}

	/**
	 * Handle license actions (activate/deactivate)
	 */
	public function handle_license_actions() {
		if ( ! isset( $_POST['your_plugin_license_action'] ) ) {
			return;
		}

		if ( ! check_admin_referer( 'your_plugin_license_action' ) ) {
			wp_die( __( 'Security check failed', 'your-plugin' ) );
		}

		$action = sanitize_text_field( $_POST['your_plugin_license_action'] );

		if ( 'activate' === $action ) {
			$this->handle_activate();
		} elseif ( 'deactivate' === $action ) {
			$this->handle_deactivate();
		}
	}

	/**
	 * Handle license activation
	 */
	protected function handle_activate() {
		$license_key = sanitize_text_field( $_POST['license_key'] ?? '' );

		if ( empty( $license_key ) ) {
			add_settings_error(
				'your_plugin_license',
				'empty_key',
				__( 'License key is required.', 'your-plugin' ),
				'error'
			);
			return;
		}

		$result = $this->license_manager->activate_license( $license_key );

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'your_plugin_license',
				$result->get_error_code(),
				$result->get_error_message(),
				'error'
			);
		} else {
			add_settings_error(
				'your_plugin_license',
				'activated',
				__( 'License activated successfully!', 'your-plugin' ),
				'success'
			);
		}
	}

	/**
	 * Handle license deactivation
	 */
	protected function handle_deactivate() {
		$result = $this->license_manager->deactivate_license();

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'your_plugin_license',
				$result->get_error_code(),
				$result->get_error_message(),
				'error'
			);
		} else {
			add_settings_error(
				'your_plugin_license',
				'deactivated',
				__( 'License deactivated successfully.', 'your-plugin' ),
				'success'
			);
		}
	}

	/**
	 * Render license settings page
	 */
	public function render_page() {
		$license_manager = $this->license_manager;
		$is_active = $license_manager->is_active();
		$license_data = $license_manager->get_features();

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors( 'your_plugin_license' ); ?>

			<div class="card">
				<h2><?php esc_html_e( 'License Status', 'your-plugin' ); ?></h2>

				<?php if ( $is_active ) : ?>
					<p style="color: green;">
						<strong><?php esc_html_e( 'Active', 'your-plugin' ); ?></strong>
					</p>
					<p>
						<?php
						printf(
							/* translators: %s: License plan */
							esc_html__( 'Plan: %s', 'your-plugin' ),
							esc_html( $license_data['plan'] ?? 'N/A' )
						);
						?>
					</p>
					<p>
						<?php
						printf(
							/* translators: %s: Expiration date */
							esc_html__( 'Expires: %s', 'your-plugin' ),
							esc_html( $license_data['expires_at'] ?? 'N/A' )
						);
						?>
					</p>

					<form method="post" action="">
						<?php wp_nonce_field( 'your_plugin_license_action' ); ?>
						<input type="hidden" name="your_plugin_license_action" value="deactivate" />
						<?php submit_button( __( 'Deactivate License', 'your-plugin' ), 'secondary' ); ?>
					</form>
				<?php else : ?>
					<p style="color: red;">
						<strong><?php esc_html_e( 'Inactive', 'your-plugin' ); ?></strong>
					</p>

					<form method="post" action="">
						<?php wp_nonce_field( 'your_plugin_license_action' ); ?>
						<input type="hidden" name="your_plugin_license_action" value="activate" />
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="license_key"><?php esc_html_e( 'License Key', 'your-plugin' ); ?></label>
								</th>
								<td>
									<input
										type="text"
										id="license_key"
										name="license_key"
										class="regular-text"
										placeholder="FCHUB-PRODUCT-XXXX-YYYY-ZZZZ"
									/>
									<p class="description">
										<?php esc_html_e( 'Enter your license key from your purchase email.', 'your-plugin' ); ?>
									</p>
								</td>
							</tr>
						</table>
						<?php submit_button( __( 'Activate License', 'your-plugin' ) ); ?>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}

// Initialize
new License_Settings();
```

---

## REST API Integration

### Add License Endpoints

Add REST API endpoints for license management:

```php
<?php
namespace YourPlugin\API;

use YourPlugin\License\YourPlugin_License_Manager;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class License_Endpoints {

	/**
	 * License manager instance
	 *
	 * @var YourPlugin_License_Manager
	 */
	protected $license_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->license_manager = new YourPlugin_License_Manager();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	protected function init_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes
	 */
	public function register_routes() {
		$namespace = 'your-plugin/v1';

		// Activate license
		register_rest_route(
			$namespace,
			'/license/activate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'activate_license' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'license_key' => array(
						'required' => true,
						'type'     => 'string',
						'validate_callback' => function( $param ) {
							return preg_match( '/^FCHUB-[A-Z]+-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $param );
						},
					),
				),
			)
		);

		// Deactivate license
		register_rest_route(
			$namespace,
			'/license/deactivate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'deactivate_license' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// Get license status
		register_rest_route(
			$namespace,
			'/license/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Check permission
	 *
	 * @return bool
	 */
	public function check_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Activate license endpoint
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function activate_license( WP_REST_Request $request ) {
		$license_key = $request->get_param( 'license_key' );

		$result = $this->license_manager->activate_license( $license_key );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'License activated successfully.', 'your-plugin' ),
				'license' => $result['license'] ?? array(),
			),
			200
		);
	}

	/**
	 * Deactivate license endpoint
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function deactivate_license( WP_REST_Request $request ) {
		$result = $this->license_manager->deactivate_license();

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'License deactivated successfully.', 'your-plugin' ),
			),
			200
		);
	}

	/**
	 * Get license status endpoint
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_status( WP_REST_Request $request ) {
		$license_manager = $this->license_manager;
		$is_active = $license_manager->is_active();
		$features = $license_manager->get_features();

		return new WP_REST_Response(
			array(
				'active'  => $is_active,
				'license' => $features,
			),
			200
		);
	}
}

// Initialize
new License_Endpoints();
```

---

## Feature Checking

### Check License Before Feature Use

Always check license before using licensed features:

```php
<?php
class YourFeature {
	
	protected $license_manager;

	public function __construct() {
		$this->license_manager = new YourPlugin_License_Manager();
	}

	/**
	 * Use a licensed feature
	 */
	public function use_licensed_feature() {
		// Check license first
		if ( ! $this->license_manager->is_active() ) {
			return new WP_Error(
				'license_required',
				__( 'Active license required to use this feature.', 'your-plugin' )
			);
		}

		// Track usage (triggers auto-validation if needed)
		$this->license_manager->track_usage();

		// Use your feature
		return $this->do_something();
	}
}
```

### Custom Feature Methods

Add custom methods to check specific features:

```php
<?php
class YourPlugin_License_Manager extends License_Manager {
	
	protected function get_product_slug(): string {
		return 'your-plugin-slug';
	}

	/**
	 * Check if video upload is enabled
	 *
	 * @return bool
	 */
	public function can_upload_video(): bool {
		if ( ! $this->is_active() ) {
			return false;
		}

		$features = $this->get_features();
		
		// For Companion: check direct fields
		if ( isset( $features['max_sites'] ) ) {
			// Companion format - check specific fields
			return isset( $features['realtime_enabled'] ) && $features['realtime_enabled'];
		}

		// For other products: check features object
		return isset( $features['video_upload'] ) && $features['video_upload'];
	}

	/**
	 * Get maximum upload size
	 *
	 * @return int Size in MB
	 */
	public function get_max_upload_size(): int {
		$features = $this->get_features();
		
		// For Companion: check direct fields
		if ( isset( $features['max_sites'] ) ) {
			// Companion doesn't have upload size limit
			return 0; // Unlimited
		}

		// For other products: check features object
		return intval( $features['max_upload_size_mb'] ?? 0 );
	}
}
```

---

## Error Handling

### Common Error Codes

The SDK returns `WP_Error` objects with specific error codes:

| Error Code | Meaning | Solution |
|------------|---------|----------|
| `invalid_license_key` | Invalid key format | Check key format: `FCHUB-PRODUCT-XXXX-YYYY-ZZZZ` |
| `no_license` | No license configured | Activate a license first |
| `NOT_FOUND` | License not found | Verify license key |
| `FORBIDDEN` | License expired/cancelled | Renew subscription |
| `CONFLICT` | Site limit exceeded | Deactivate from another site |
| `json_error` | Invalid API response | Check API connectivity |

### Error Handling Example

```php
<?php
$license = new YourPlugin_License_Manager();

// Activate license
$result = $license->activate_license( $license_key );

if ( is_wp_error( $result ) ) {
	$error_code = $result->get_error_code();
	$error_message = $result->get_error_message();

	switch ( $error_code ) {
		case 'invalid_license_key':
			// Show format error
			echo '<div class="error">Invalid license key format.</div>';
			break;

		case 'NOT_FOUND':
			// License doesn't exist
			echo '<div class="error">License not found. Please check your key.</div>';
			break;

		case 'FORBIDDEN':
			// License expired/cancelled
			echo '<div class="error">License expired or cancelled. Please renew.</div>';
			break;

		case 'CONFLICT':
			// Site limit exceeded
			echo '<div class="error">Site limit exceeded. Deactivate from another site first.</div>';
			break;

		default:
			// Generic error
			echo '<div class="error">' . esc_html( $error_message ) . '</div>';
			break;
	}
} else {
	// Success
	echo '<div class="success">License activated successfully!</div>';
}
```

---

## Best Practices

### 1. Singleton Pattern

Use singleton pattern to avoid multiple instances:

```php
<?php
class YourPlugin_License_Manager extends License_Manager {
	
	private static $instance = null;

	protected function get_product_slug(): string {
		return 'your-plugin-slug';
	}

	/**
	 * Get instance
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization
	 */
	public function __wakeup() {
		throw new Exception( 'Cannot unserialize singleton' );
	}
}

// Usage
$license = YourPlugin_License_Manager::get_instance();
```

### 2. Cache License Status

Cache license status to avoid repeated checks:

```php
<?php
class YourFeature {
	
	protected $license_manager;
	protected $license_cache = null;

	public function __construct() {
		$this->license_manager = new YourPlugin_License_Manager();
	}

	/**
	 * Check if license is active (cached)
	 *
	 * @return bool
	 */
	protected function is_licensed() {
		if ( null === $this->license_cache ) {
			$this->license_cache = $this->license_manager->is_active();
		}
		return $this->license_cache;
	}
}
```

### 3. Graceful Degradation

Always provide fallback for unlicensed users:

```php
<?php
if ( $license->is_active() ) {
	// Full feature set
	do_premium_feature();
} else {
	// Free/basic version
	do_basic_feature();
	show_upgrade_notice();
}
```

### 4. Security

Never expose license keys in frontend:

```php
// ❌ BAD - Don't do this
wp_localize_script( 'my-script', 'license', array(
	'key' => $license_key, // NEVER expose license key!
) );

// ✅ GOOD - Only expose status
wp_localize_script( 'my-script', 'license', array(
	'active' => $license->is_active(),
) );
```

---

## Examples

### Example 1: Simple Plugin

```php
<?php
// includes/class-simple-license-manager.php
namespace SimplePlugin\License;

use FCHub\License\License_Manager;

class Simple_License_Manager extends License_Manager {
	protected function get_product_slug(): string {
		return 'simple-plugin';
	}
}

// main-plugin-file.php
use SimplePlugin\License\Simple_License_Manager;

$license = new Simple_License_Manager();

// Check before feature
if ( $license->is_active() ) {
	add_action( 'init', 'enable_premium_features' );
}
```

### Example 2: Advanced Plugin with Features

```php
<?php
// includes/class-advanced-license-manager.php
namespace AdvancedPlugin\License;

use FCHub\License\License_Manager;

class Advanced_License_Manager extends License_Manager {
	
	protected function get_product_slug(): string {
		return 'advanced-plugin';
	}

	public function can_use_feature_a(): bool {
		return $this->is_active() && 
		       $this->is_feature_enabled( 'feature_a' );
	}

	public function can_use_feature_b(): bool {
		return $this->is_active() && 
		       $this->is_feature_enabled( 'feature_b' );
	}

	protected function is_feature_enabled( string $feature ): bool {
		$features = $this->get_features();
		
		// Handle Companion format (direct fields)
		if ( isset( $features['max_sites'] ) ) {
			// Companion - check specific fields
			return true; // Or check specific Companion fields
		}

		// Handle other products (features object)
		return isset( $features[ $feature ] ) && $features[ $feature ];
	}
}
```

### Example 3: REST API Integration

```php
<?php
// api/class-license-controller.php
namespace AdvancedPlugin\API;

use AdvancedPlugin\License\Advanced_License_Manager;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class License_Controller {
	
	protected $license;

	public function __construct() {
		$this->license = new Advanced_License_Manager();
	}

	public function check_feature( WP_REST_Request $request ) {
		$feature = $request->get_param( 'feature' );

		if ( ! $this->license->is_active() ) {
			return new WP_Error(
				'license_required',
				'Active license required',
				array( 'status' => 403 )
			);
		}

		$can_use = method_exists( $this->license, "can_use_{$feature}" )
			? call_user_func( array( $this->license, "can_use_{$feature}" ) )
			: false;

		return new WP_REST_Response(
			array(
				'allowed' => $can_use,
			),
			200
		);
	}
}
```

---

## Troubleshooting

### License Not Activating

1. **Check license key format:** Must be `FCHUB-PRODUCT-XXXX-YYYY-ZZZZ`
2. **Check product slug:** Must match exactly what's in FCHub dashboard
3. **Check site URL:** Must match exactly (with/without trailing slash)
4. **Check API connectivity:** Verify `https://api.fchub.co/rpc` is accessible

### License Validation Failing

1. **Check grace period:** License works offline for 7 days
2. **Check network:** API must be reachable for validation
3. **Check license status:** License might be expired/cancelled
4. **Check logs:** Enable WordPress debug logging

### Features Not Working

1. **Check response format:** Companion vs other products have different formats
2. **Check feature names:** Must match exactly what's in license
3. **Check license status:** Must be active
4. **Check storage:** License data might be corrupted (clear and reactivate)

---

## Support

For issues or questions:

- **Documentation:** See `README.md` for API reference
- **Issues:** Report on GitHub
- **Email:** support@fchub.co

---

## License

This SDK is part of the FCHub License System. See LICENSE file for details.

