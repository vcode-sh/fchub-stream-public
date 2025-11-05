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
	'name'                      => 'FCHub Stream',

	/**
	 * Plugin slug identifier.
	 *
	 * @since 0.0.1
	 */
	'slug'                      => 'fchub-stream',

	/**
	 * Language files directory path.
	 *
	 * @since 0.0.1
	 */
	'domain_path'               => '/language',

	/**
	 * Internationalization text domain.
	 *
	 * @since 0.0.1
	 */
	'text_domain'               => 'fchub-stream',

	/**
	 * WordPress hook prefix for actions and filters.
	 *
	 * @since 0.0.1
	 */
	'hook_prefix'               => 'fchub-stream',

	/**
	 * REST API namespace for FluentCommunity integration.
	 *
	 * @since 0.0.1
	 */
	'rest_namespace'            => 'fluent-community',

	/**
	 * REST API version.
	 *
	 * @since 0.0.1
	 */
	'rest_version'              => 'v2',

	/**
	 * Application environment (dev/production).
	 *
	 * @since 0.0.1
	 */
	'env'                       => 'dev',

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
	'sentry_dsn'                => 'https://65624d321efcf8b9c3cccd4634deecba@o4509803635736576.ingest.de.sentry.io/4510309129584720',

	/**
	 * Sentry traces sample rate (developer only).
	 *
	 * Controls what percentage of performance traces are sent to Sentry.
	 * Set to null to use environment-based defaults:
	 * - development/local: 1.0 (100%)
	 * - staging/beta: 0.7 (70%)
	 * - production: 0.1 (10%)
	 *
	 * Set a specific value (0.0-1.0) to override environment-based defaults.
	 * Example: 0.7 for beta testing (70% visibility).
	 *
	 * @since 1.0.0
	 */
	'sentry_traces_sample_rate' => 0.7, // 70% for beta testing - good visibility for 30 testers.

	/**
	 * PostHog API Key for analytics tracking (developer only).
	 *
	 * This API key is used to send analytics events from ALL installations
	 * (including beta testers) to the developer's PostHog project.
	 * End users do NOT configure PostHog - it's automatic.
	 *
	 * Set this to your PostHog project API key to enable analytics tracking.
	 * Leave empty to disable PostHog.
	 *
	 * @since 1.1.0
	 */
	'posthog_api_key'           => 'phc_zK8i25Gq2Fjmb1md9oDmjwmo1FIfgQLo80m9onu7Rs6',

	/**
	 * PostHog host URL.
	 *
	 * Defaults to EU cloud (https://eu.i.posthog.com).
	 * Change if using self-hosted PostHog or US cloud.
	 *
	 * @since 1.1.0
	 */
	'posthog_host'              => 'https://eu.i.posthog.com',
);
