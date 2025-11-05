<?php
/**
 * Application configuration.
 *
 * Defines core application settings for the FCHub Stream plugin,
 * including plugin metadata, REST API configuration, and environment settings.
 *
 * @package FCHub_Stream
 * @subpackage Config
 * @since 0.0.1
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	/**
	 * Plugin display name.
	 *
	 * @since 0.0.1
	 */
	'name'           => 'FCHub Stream',

	/**
	 * Plugin slug identifier.
	 *
	 * @since 0.0.1
	 */
	'slug'           => 'fchub-stream',

	/**
	 * Language files directory path.
	 *
	 * @since 0.0.1
	 */
	'domain_path'    => '/language',

	/**
	 * Internationalization text domain.
	 *
	 * @since 0.0.1
	 */
	'text_domain'    => 'fchub-stream',

	/**
	 * WordPress hook prefix for actions and filters.
	 *
	 * @since 0.0.1
	 */
	'hook_prefix'    => 'fchub-stream',

	/**
	 * REST API namespace for FluentCommunity integration.
	 *
	 * @since 0.0.1
	 */
	'rest_namespace' => 'fluent-community',

	/**
	 * REST API version.
	 *
	 * @since 0.0.1
	 */
	'rest_version'   => 'v2',

	/**
	 * Application environment (dev/production).
	 *
	 * @since 0.0.1
	 */
	'env'            => 'dev',

	/**
	 * Sentry DSN for error monitoring (developer only).
	 *
	 * This DSN is used to send error reports from ALL installations
	 * (including beta testers) to the developer's Sentry project.
	 * End users do NOT configure Sentry - it's automatic.
	 *
	 * Set this to your Sentry DSN to enable error tracking.
	 * Leave empty to disable Sentry.
	 *
	 * @since 1.0.0
	 */
	'sentry_dsn'     => 'https://65624d321efcf8b9c3cccd4634deecba@o4509803635736576.ingest.de.sentry.io/4510309129584720',
);
