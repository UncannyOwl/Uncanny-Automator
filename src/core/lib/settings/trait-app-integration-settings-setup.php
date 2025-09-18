<?php

namespace Uncanny_Automator\Settings;

/**
 * Trait for App Integration Settings setup functionality
 *
 * This trait contains setup methods that can be shared between
 * free and Pro settings classes to maintain DRY principles.
 *
 * @package Uncanny_Automator\Settings
 */
trait App_Integration_Settings_Setup {

	/**
	 * The settings ID of the integration ( url param / option key etc. )
	 *
	 * @var String
	 */
	public $id;

	/**
	 * The integration id.
	 *
	 * @var String
	 */
	public $integration;

	/**
	 * The icon of the integration
	 * This expects a valid <uo-icon> ID.
	 * Check the Design Guidelines to see the list of valid IDs.
	 *
	 * @var String
	 */
	public $icon;

	/**
	 * The name of the integration
	 *
	 * @var String
	 */
	public $name;

	/**
	 * Whether the integration is connected
	 *
	 * @var bool
	 */
	public $is_connected = false;

	/**
	 * Relative path of the JS file that loads only in the settings page
	 * of this premium integration
	 *
	 * @var String
	 */
	public $js = '';

	/**
	 * Relative path of the CSS file that loads only in the settings page
	 * of this premium integration
	 *
	 * @var String
	 */
	public $css = '';

	/**
	 * Set the settings page properties.
	 * This method is meant to be overridden by the child class if needed.
	 *
	 * @return void
	 */
	public function set_properties() {}

	/**
	 * Register additional hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {}

	/**
	 * Sets the settings page ID of the integration
	 * This will also be used as the tab ID
	 *
	 * @param String $id The ID
	 */
	public function set_id( $id ) {
		// Check if the ID is defined
		if ( empty( $id ) ) {
			throw new \Exception( "Premium Integration: The ID can't be empty" );
		}

		$this->id = $id;
	}

	/**
	 * Returns the settings page ID of the integration
	 *
	 * @return String The settings page ID
	 */
	public function get_id() {
		// Check if the ID is defined
		if ( empty( $this->id ) ) {
			throw new \Exception( "Premium Integration: The ID can't be empty" );
		}

		return $this->id;
	}

	/**
	 * Sets the integration ID
	 *
	 * @param String $integration The integration ID
	 */
	public function set_integration( $integration ) {
		$this->integration = $integration;
	}

	/**
	 * Returns the integration ID
	 *
	 * @return String The integration ID
	 */
	public function get_integration() {
		return $this->integration;
	}

	/**
	 * Sets the name of the integration
	 *
	 * @param String $name The name
	 */
	public function set_name( $name ) {
		// Check if the name is defined
		if ( empty( $name ) ) {
			throw new \Exception( "Premium Integration: The name can't be empty" );
		}

		$this->name = $name;
	}

	/**
	 * Returns the integration name
	 *
	 * @return String The integration name
	 */
	public function get_name() {
		// Check if the name is defined
		if ( empty( $this->name ) ) {
			throw new \Exception( "Premium Integration: The name can't be empty" );
		}

		return $this->name;
	}

	/**
	 * Sets the icon of the integration
	 * This expects a valid <uo-icon> ID.
	 * Check the Design Guidelines to see the list of valid IDs.
	 *
	 * @param String $icon The icon
	 */
	public function set_icon( $icon ) {
		$this->icon = $icon;
	}

	/**
	 * Returns the integration icon
	 *
	 * @return String The integration icon
	 */
	public function get_icon() {
		// As the property is optional, we will return a default value if it's not defined
		return ! empty( $this->icon ) ? $this->icon : 'bolt';
	}

	/**
	 * Sets a JS file that loads only on the settings page of this
	 * premium integration
	 *
	 * @param String $js The path of the JS file.
	 */
	public function set_js( $js ) {
		$this->js = $js;
	}

	/**
	 * Returns the path of the JS file of this settings page
	 *
	 * @return String The JS file path
	 */
	public function get_js() {
		return $this->js;
	}

	/**
	 * Sets a CSS file that loads only on the settings page of this
	 * premium integration
	 *
	 * @param String $css The path of the CSS file.
	 */
	public function set_css( $css ) {
		$this->css = $css;
	}

	/**
	 * Returns the path of the CSS file of this settings page
	 *
	 * @return String The CSS file path
	 */
	public function get_css() {
		return $this->css;
	}

	/**
	 * Sets the connection status of the integration
	 *
	 * @param bool $is_connected The connection status
	 *
	 * @return void
	 */
	public function set_is_connected( $is_connected ) {
		$this->is_connected = (bool) $is_connected;
	}

	/**
	 * Returns the connection status of the integration
	 *
	 * @return bool The connection status
	 */
	public function get_is_connected() {
		return $this->is_connected;
	}

	/**
	 * Returns whether the integration is a third party integration
	 *
	 * @return boolean TRUE if the integration is a third party integration
	 */
	public function get_is_third_party() {
		return $this->is_third_party;
	}

	/**
	 * Sets whether the integration is a third party integration
	 *
	 * @param boolean $is_third_party TRUE if the integration is a third party integration
	 *
	 * @return void
	 */
	public function set_is_third_party( $is_third_party ) {
		$this->is_third_party = $is_third_party;
	}

	/**
	 * Returns the URL to the Settings page of this integration
	 *
	 * @param array $params Optional. Additional parameters to add to the URL.
	 *
	 * @return String The URL
	 */
	public function get_settings_page_url( $params = array() ) {
		return $this->helpers->get_settings_page_url( $params );
	}

	/**
	 * Set the connected properties.
	 * This method is meant to be overridden by the child class if needed.
	 *
	 * @return void
	 */
	public function set_connected_properties() {}

	/**
	 * Set the disconnected properties.
	 * This method is meant to be overridden by the child class if needed.
	 *
	 * @return void
	 */
	public function set_disconnected_properties() {}

	/**
	 * Handles conditional option registration based on connection status.
	 * This method will call register_options() and then conditionally call
	 * register_connected_options() or register_disconnected_options() based on connection status.
	 *
	 * @return void
	 */
	public function option_registration() {
		// Register options independent of connection status.
		$this->register_options();

		// Register connection status specific options
		if ( $this->get_is_connected() ) {
			$this->register_connected_options();
			return;
		}

		// Register options that should only be available when the integration is disconnected.
		$this->register_disconnected_options();
	}

	/**
	 * Register options that should only be available when the integration is connected.
	 * Override this method in the extending class to register specific options.
	 *
	 * @return void
	 */
	protected function register_connected_options() {
		// Default implementation does nothing
	}

	/**
	 * Register options that should only be available when the integration is disconnected.
	 * Override this method in the extending class to register specific options.
	 *
	 * @return void
	 */
	protected function register_disconnected_options() {
		// Default implementation does nothing
	}

	/**
	 * Determines whether the user is currently in the Settings page of the integration
	 *
	 * @return bool TRUE if it is
	 */
	public function is_current_page_settings() {
		return automator_filter_input( 'page' ) === 'uncanny-automator-config'
		&& automator_filter_input( 'tab' ) === 'premium-integrations'
		&& automator_filter_input( 'integration' ) === $this->get_id();
	}

	/**
	 * Enqueue the assets
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		// Check if there are assets defined
		if ( ! $this->get_css() && ! $this->get_js() ) {
			return;
		}

		// Only enqueue the assets of this integration on its own settings page
		if ( ! $this->is_current_page_settings() ) {
			return;
		}

		// Enqueue the CSS
		if ( $this->get_css() ) {
			$this->load_css( $this->get_css() );
		}

		// Enqueue the JS
		if ( $this->get_js() ) {
			$this->load_js( $this->get_js() );
		}
	}

	/**
	 * Add scripts on the settings page output
	 *
	 * @param string $path Required. The path relative to '/src/integrations/'.
	 * @param string $post_fix Optional. Useful for loading multiple JS files.
	 *
	 * @return void
	 */
	public function load_js( $path, $post_fix = '' ) {

		$handle = 'uap-premium-integration-' . $this->get_id() . $post_fix;

		wp_enqueue_script(
			$handle,
			plugins_url( '/src/integrations/' . $path, AUTOMATOR_BASE_FILE ),
			array( 'uap-admin' ),
			AUTOMATOR_PLUGIN_VERSION,
			true
		);
	}

	/**
	 * Add styles on the settings page output
	 *
	 * @param  string $path
	 * @return void
	 */
	public function load_css( $path ) {
		wp_enqueue_style(
			'uap-premium-integration-' . $this->get_id(),
			plugins_url( '/src/integrations/' . $path, AUTOMATOR_BASE_FILE ),
			array( 'uap-admin' ),
			AUTOMATOR_PLUGIN_VERSION
		);
	}

	/**
	 * Get all registered options including connected and disconnected states
	 *
	 * @return array All registered options
	 */
	protected function get_all_registered_options() {
		// Store current options
		$current_options = $this->options;

		// Register all possible options
		$this->register_options();
		$this->register_connected_options();
		$this->register_disconnected_options();

		// Merge with current options, preserving existing settings
		$all_options = array_merge( $current_options, $this->options );

		// Restore original options
		$this->options = $current_options;

		return $all_options;
	}

	/**
	 * Get the posted row ID from the request
	 * - Helper method for uo-settings-table component
	 *
	 * @return mixed - The posted row ID as defined in the settings table component
	 */
	public function maybe_get_posted_row_id( $data = array() ) {

		// Data is passed from the REST settings table component.
		if ( ! empty( $data ) ) {
			return $data['row_id'] ?? '';
		}

		// Standard POST request -- todo remove this - no longer being used
		return automator_filter_has_var( 'row_id', INPUT_POST )
			? automator_filter_input( 'row_id', INPUT_POST )
			: '';
	}

	/**
	 * Set multiple properties from an array of settings
	 *
	 * @param array $settings Array of settings to set
	 * @return void
	 */
	public function set_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return;
		}

		if ( ! empty( $settings['id'] ) ) {
			$this->set_id( $settings['id'] );
		}

		if ( ! empty( $settings['integration'] ) ) {
			$this->set_integration( $settings['integration'] );
		}

		if ( ! empty( $settings['icon'] ) ) {
			$this->set_icon( $settings['icon'] );
		}

		if ( ! empty( $settings['name'] ) ) {
			$this->set_name( $settings['name'] );
		}

		if ( isset( $settings['is_connected'] ) ) {
			$this->set_is_connected( $settings['is_connected'] );
		}

		if ( isset( $settings['is_third_party'] ) ) {
			$this->set_is_third_party( $settings['is_third_party'] );
		}
	}
}
