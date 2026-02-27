<?php

namespace Uncanny_Automator;

/**
 * Class Admin_Settings_Uncanny_Agent_General
 *
 * @since   7.0
 * @version 1.0
 * @package Uncanny_Automator
 */
class Admin_Settings_Uncanny_Agent_General {

	const SETTINGS_GROUP = 'uncanny_automator_uncanny_agent';
	const OPTION_NAME    = 'automator_uncanny_agent_settings';
	const ENABLED_KEY    = 'enabled';

	/**
	 * Class constructor
	 */
	public function __construct() {
		// Define the tab.
		$this->create_tab();

		// Register the setting.
		add_action(
			'admin_init',
			function () {
				register_setting(
					self::SETTINGS_GROUP,
					self::OPTION_NAME,
					array(
						'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
					)
				);
			}
		);
	}

	/**
	 * Returns the default settings array.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return array(
			self::ENABLED_KEY => true,
		);
	}

	/**
	 * Get a single setting value by key.
	 *
	 * Single source of truth for reading Uncanny Agent settings.
	 *
	 * @param string $key Setting key to retrieve.
	 *
	 * @return mixed The setting value, or the default if not set.
	 */
	public static function get_setting( string $key ) {
		$defaults = self::get_defaults();
		$settings = automator_get_option( self::OPTION_NAME, $defaults );

		if ( ! is_array( $settings ) ) {
			$settings = $defaults;
		}

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : ( $defaults[ $key ] ?? null );
	}

	/**
	 * Sanitize callback for the settings array.
	 *
	 * @param mixed $value The raw value from the form submission.
	 *
	 * @return array<string, mixed> Sanitized settings.
	 */
	public static function sanitize_settings( $value ): array {
		$defaults  = self::get_defaults();
		$sanitized = array();

		$sanitized[ self::ENABLED_KEY ] = ! empty( $value[ self::ENABLED_KEY ] );

		return wp_parse_args( $sanitized, $defaults );
	}

	/**
	 * Adds the tab using the automator_settings_uncanny_agent_tabs filter.
	 */
	private function create_tab() {
		add_filter(
			'automator_settings_uncanny_agent_tabs',
			function ( $tabs ) {
				// General.
				$tabs['general'] = (object) array(
					'name'     => esc_html__( 'General', 'uncanny-automator' ),
					'function' => array( $this, 'tab_output' ),
					'preload'  => false, // Determines if the content should be loaded even if the tab is not selected.
					'icon'     => 'cog',
				);

				return $tabs;
			},
			10,
			1
		);
	}

	/**
	 * Outputs the content of the "General" tab.
	 */
	public function tab_output() {
		$is_enabled = self::get_setting( self::ENABLED_KEY );

		// Check if updated and reset the state.
		$updated = $this->save_setting();
		if ( null !== $updated ) {
			$is_enabled = $updated;
		}

		// Load the view.
		include Utilities::automator_get_view( 'admin-settings/tab/uncanny-agent/general.php' );
	}

	/**
	 * Saves the enable/disable setting.
	 *
	 * @return bool|null The new state, or null if not saving.
	 */
	private function save_setting() {
		// Defense-in-depth capability check.
		if ( ! current_user_can( 'manage_options' ) ) {
			return null;
		}

		$key = self::SETTINGS_GROUP;

		// Check if we are on the correct options page.
		if ( ! automator_filter_has_var( 'option_page', INPUT_POST ) ) {
			return null;
		}

		if ( automator_filter_input( 'option_page', INPUT_POST ) !== $key ) {
			return null;
		}

		// Validate the nonce.
		if ( ! automator_filter_has_var( '_wpnonce', INPUT_POST ) ) {
			return null;
		}

		if ( ! wp_verify_nonce( automator_filter_input( '_wpnonce', INPUT_POST ), "{$key}-options" ) ) {
			return null;
		}

		// Read existing settings.
		$settings = automator_get_option( self::OPTION_NAME, self::get_defaults() );

		if ( ! is_array( $settings ) ) {
			$settings = self::get_defaults();
		}

		// Check if the toggle is present and enabled.
		if ( automator_filter_has_var( self::ENABLED_KEY, INPUT_POST ) && automator_filter_input( self::ENABLED_KEY, INPUT_POST, FILTER_VALIDATE_BOOLEAN ) ) {
			$settings[ self::ENABLED_KEY ] = true;
			automator_update_option( self::OPTION_NAME, $settings );
			return true;
		}

		// Toggle is off — save as disabled.
		$settings[ self::ENABLED_KEY ] = false;
		automator_update_option( self::OPTION_NAME, $settings );
		return false;
	}
}

new Admin_Settings_Uncanny_Agent_General();
