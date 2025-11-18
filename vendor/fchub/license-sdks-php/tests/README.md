# FCHub License SDK - PHP Tests

This directory contains PHPUnit tests for the FCHub License SDK PHP package.

## Test Structure

```
tests/
├── bootstrap.php                 # Test bootstrap (WordPress mocks)
├── License_Storage_Test.php      # Storage encryption tests
├── License_API_Client_Test.php   # API client tests
└── License_Manager_Test.php      # License manager tests
```

## Running Tests

### All Tests
```bash
composer test
```

### Specific Test
```bash
./vendor/bin/phpunit tests/License_Storage_Test.php
```

### With Coverage
```bash
composer test:coverage
```
Coverage report will be generated in `coverage/` directory.

## Test Coverage

**26 tests, 53 assertions** covering:

### License_Storage Tests (8 tests)
- ✅ Option name formatting
- ✅ Save/get/clear operations
- ✅ Encryption/decryption cycle
- ✅ Product-specific storage isolation
- ✅ Null returns for empty data

### License_API_Client Tests (5 tests)
- ✅ Client instantiation
- ✅ Activate endpoint
- ✅ Validate endpoint
- ✅ Deactivate endpoint
- ✅ Parameter formatting

### License_Manager Tests (13 tests)
- ✅ Manager instantiation
- ✅ License key format validation (valid/invalid)
- ✅ Activation with valid/invalid keys
- ✅ is_active() states
- ✅ get_features() states
- ✅ validate_license() states
- ✅ deactivate_license() functionality
- ✅ Grace period logic

## Mocked WordPress Functions

The test suite mocks WordPress functions for isolated testing:

- `get_site_url()` - Returns test URL
- `get_option()` / `update_option()` / `delete_option()` - In-memory storage
- `current_time()` - Returns current time
- `wp_json_encode()` - JSON encoding
- `wp_remote_post()` - Simulates API responses
- `__()` - Translation (passthrough)
- `WP_Error` class - Error handling
- `is_wp_error()` - Error detection

## WordPress Constants

Defined in `bootstrap.php`:

- `ABSPATH` - WordPress root path
- `DAY_IN_SECONDS` - Seconds in day (86400)
- `AUTH_KEY` - Encryption key (test value)
- `AUTH_SALT` - Encryption salt (test value)

## Adding New Tests

1. Create test file: `tests/YourClass_Test.php`
2. Extend `PHPUnit\Framework\TestCase`
3. Use namespace: `FCHub\License\Tests`
4. Follow naming: `test_method_name_description()`
5. Run: `composer test`

## CI/CD Integration

Tests can be run in CI/CD pipelines:

```yaml
# GitHub Actions example
- name: Install dependencies
  run: composer install

- name: Run PHPCS
  run: composer lint

- name: Run PHPUnit
  run: composer test
```

## Requirements

- PHP >= 8.3
- PHPUnit 10.x
- Mockery 1.6+

## Notes

- Tests use in-memory storage (no database required)
- WordPress functions are mocked (no WordPress installation needed)
- API calls are mocked (no external dependencies)
- All tests are isolated and can run in any order
