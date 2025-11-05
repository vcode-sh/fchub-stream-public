<?php
/**
 * Admin interface management.
 *
 * Handles WordPress admin integration including menu registration,
 * settings pages, and Vue-based admin UI rendering for stream configuration.
 *
 * @package FCHub_Stream
 * @subpackage Admin
 * @since 1.0.0
 */

namespace FCHubStream\App\Admin;

/**
 * Admin class.
 *
 * Manages WordPress admin interface integration for the FCHub Stream plugin.
 * Provides menu registration, page rendering, and Vue app integration.
 *
 * @since 1.0.0
 */
class Admin {
	/**
	 * Plugin name identifier.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $version;

	/**
	 * Constructor.
	 *
	 * Initializes admin interface with plugin details.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_name Plugin identifier.
	 * @param string $version Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register admin menu pages.
	 *
	 * Adds top-level menu and submenu pages for stream configuration.
	 * Creates a dedicated "Stream" menu with Welcome and Settings subpages.
	 *
	 * Hooked to 'admin_menu' action via app/Hooks/actions.php.
	 *
	 * @since 1.0.0
	 * @hook admin_menu
	 *
	 * @return void
	 */
	public function add_plugin_admin_menu() {
		// Add top-level menu.
		add_menu_page(
			__( 'FCHub Stream', 'fchub-stream' ),
			__( 'FCHub Stream', 'fchub-stream' ),
			'manage_options',
			'fchub-stream',
			array( $this, 'display_admin_page' ),
			'dashicons-video-alt3',
			31
		);

		// Add submenu pages.
		add_submenu_page(
			'fchub-stream',
			__( 'Welcome', 'fchub-stream' ),
			__( 'Welcome', 'fchub-stream' ),
			'manage_options',
			'fchub-stream',
			array( $this, 'display_admin_page' )
		);

		add_submenu_page(
			'fchub-stream',
			__( 'Settings', 'fchub-stream' ),
			__( 'Settings', 'fchub-stream' ),
			'manage_options',
			'fchub-stream-settings',
			array( $this, 'display_settings_page' )
		);
	}

	/**
	 * Display admin page (Welcome).
	 *
	 * Renders the default welcome page using Vue app.
	 * Registered as menu callback via add_menu_page() and add_submenu_page().
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function display_admin_page() {
		$this->load_vue_app( 'welcome' );
	}

	/**
	 * Display settings page.
	 *
	 * Renders the settings page using Vue app.
	 * Registered as menu callback via add_submenu_page().
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function display_settings_page() {
		$this->load_vue_app( 'settings' );
	}

	/**
	 * Load Vue app with specific component.
	 *
	 * Sets up component filter and loads the Vue admin template.
	 * Uses high priority filter to ensure component selection happens
	 * before the Vue template applies it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $component Component name to load ('welcome' or 'settings').
	 *
	 * @return void
	 */
	private function load_vue_app( $component ) {
		// Pass component info to Vue app.
		// Use high priority to ensure it runs before admin-vue.php applies the filter.
		add_filter(
			'fchub_stream_admin_component',
			function () use ( $component ) {
				return $component;
			},
			999 // High priority to ensure it runs.
		);

		// Load Vue app template (will exit after rendering).
		require_once FCHUB_STREAM_DIR . 'admin/admin-vue.php';
	}
}
