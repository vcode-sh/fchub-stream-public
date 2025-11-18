# FCHub License SDK - PHP

Universal license management SDK for FCHub WordPress plugins.

## Installation

```bash
composer require fchub/license-sdks-php
```

## Quick Start

```php
<?php
use FCHub\License\License_Manager;

class StreamLicenseManager extends License_Manager {
    protected function get_product_slug(): string {
        return 'fchub-stream';
    }
}

$license = new StreamLicenseManager();
$license->activate_license('FCHUB-STREAM-XXXX-YYYY-ZZZZ');

// Check if active
if ($license->is_active()) {
    // Your licensed feature code
}
```

## Documentation

- **[Implementation Guide](IMPLEMENTATION_GUIDE.md)** - Complete guide with examples
  - Quick start
  - Admin panel integration
  - REST API integration
  - Feature checking
  - Error handling
  - Best practices
  - Real-world examples

## Features

- ✅ **License Activation** - Activate licenses on WordPress sites
- ✅ **License Validation** - Automatic background validation (24h or 500 uses)
- ✅ **License Deactivation** - Deactivate and release licenses
- ✅ **Encrypted Storage** - AES-256-CBC encryption for license data
- ✅ **Grace Period** - 7 days offline tolerance
- ✅ **Product Support** - Multi-product license system
- ✅ **Error Handling** - Comprehensive error codes and messages
- ✅ **Security Reporting** - Report tampering, bypass attempts, and suspicious activity

## API Reference

### License_Manager Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `activate_license($key, $site_url = null)` | Activate license | `array\|WP_Error` |
| `validate_license()` | Validate license status | `array\|WP_Error` |
| `deactivate_license()` | Deactivate license | `array\|WP_Error` |
| `is_active()` | Check if license is active | `bool` |
| `get_features()` | Get license features | `array` |
| `track_usage()` | Track feature usage | `void` |

### Security Reporting Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `report_tampering($params)` | Report file tampering detected | `array\|WP_Error` |
| `report_bypass_attempt($params)` | Report bypass attempt (honeypot) | `array\|WP_Error` |
| `report_suspicious_activity($params)` | Report suspicious activity | `array\|WP_Error` |

**Security reporting is critical for license protection.** These methods should be called when:
- File integrity checks detect modifications
- Honeypot functions are called (indicates bypass attempt)
- Suspicious activity is detected (license checks disabled, storage tampered, etc.)

### Response Formats

**Companion Products:**
```php
[
    'license' => [
        'key' => 'FCHUB-COMPANION-XXXX-YYYY-ZZZZ',
        'plan' => 'pro',
        'expires_at' => '2026-01-01T00:00:00Z',
        'max_sites' => 1,
        'max_connections' => 500,
        // ... direct fields
    ]
]
```

**Other Products (Stream, etc.):**
```php
[
    'license' => [
        'key' => 'FCHUB-STREAM-XXXX-YYYY-ZZZZ',
        'plan' => 'pro',
        'expires_at' => '2026-01-01T00:00:00Z',
        'product' => 'fchub-stream',
        'features' => [
            'video_upload' => true,
            'max_video_size_gb' => 10,
            // ... feature object
        ]
    ]
]
```

## WordPress Coding Standards

This package follows WordPress Coding Standards.

**Run linting:**
```bash
composer lint
```

**Auto-fix:**
```bash
composer fix
```

## Examples

See [IMPLEMENTATION_GUIDE.md](IMPLEMENTATION_GUIDE.md) for complete examples including:
- Basic implementation
- Admin panel integration
- REST API endpoints
- Feature checking
- Error handling

