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

	const SETTINGS_GROUP             = 'uncanny_automator_uncanny_agent';
	const OPTION_NAME                = 'automator_uncanny_agent_settings';
	const ENABLED_KEY                = 'enabled';
	const TOP_BAR_BUTTON_ENABLED_KEY = 'top_bar_button_enabled';

	/**
	 * Result from the current save request.
	 *
	 * @var array{enabled: bool, top_bar_button_enabled: bool, saved: bool}|null
	 */
	private $save_result = null;

	/**
	 * Class constructor
	 */
	public function __construct() {
		// Define the tab.
		$this->create_tab();

		add_action( 'admin_init', array( $this, 'maybe_save_setting' ) );
	}

	/**
	 * Returns the default settings array.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return array(
			self::ENABLED_KEY                => true,
			self::TOP_BAR_BUTTON_ENABLED_KEY => true,
		);
	}

	/**
	 * Get a single setting value by key.
	 *
	 * Single source of truth for reading Uncanny Agent settings.
	 *
	 * @param string $key   Setting key to retrieve.
	 * @param bool   $force Get the value from the database.
	 *
	 * @return mixed The setting value, or the default if not set.
	 */
	public static function get_setting( string $key, bool $force = false ) {
		$settings = self::get_settings( $force );

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : null;
	}

	/**
	 * Get all Uncanny Agent presentation settings.
	 *
	 * @param bool $force Get the value from the database.
	 *
	 * @return array{enabled: bool, top_bar_button_enabled: bool}
	 */
	public static function get_settings( bool $force = false ): array {
		$defaults = self::get_defaults();
		$settings = automator_get_option( self::OPTION_NAME, $defaults, $force );

		if ( ! is_array( $settings ) ) {
			$settings = $defaults;
		}

		return array(
			self::ENABLED_KEY                => array_key_exists( self::ENABLED_KEY, $settings )
				? (bool) $settings[ self::ENABLED_KEY ]
				: $defaults[ self::ENABLED_KEY ],
			self::TOP_BAR_BUTTON_ENABLED_KEY => array_key_exists( self::TOP_BAR_BUTTON_ENABLED_KEY, $settings )
				? (bool) $settings[ self::TOP_BAR_BUTTON_ENABLED_KEY ]
				: $defaults[ self::TOP_BAR_BUTTON_ENABLED_KEY ],
		);
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
		$is_launcher_enabled       = self::get_setting( self::ENABLED_KEY );
		$is_top_bar_button_enabled = self::get_setting( self::TOP_BAR_BUTTON_ENABLED_KEY );
		$save_failed               = false;

		if ( null !== $this->save_result ) {
			$is_launcher_enabled       = $this->save_result[ self::ENABLED_KEY ];
			$is_top_bar_button_enabled = $this->save_result[ self::TOP_BAR_BUTTON_ENABLED_KEY ];
			$save_failed               = ! $this->save_result['saved'];
		}

		// Load the view.
		include Utilities::automator_get_view( 'admin-settings/tab/uncanny-agent/general.php' );
	}

	/**
	 * Save the settings and redirect successful requests to a fresh GET.
	 *
	 * @return void
	 */
	public function maybe_save_setting() {
		$this->save_result = $this->save_setting();

		if ( null === $this->save_result || ! $this->save_result['saved'] ) {
			return;
		}

		wp_safe_redirect(
			Admin_Settings_Uncanny_Agent::utility_get_uncanny_agent_page_link( 'general' ),
			303,
			'Uncanny Automator'
		);
		exit;
	}

	/**
	 * Save the launcher settings.
	 *
	 * @return array{enabled: bool, top_bar_button_enabled: bool, saved: bool}|null Save result, or null when no save occurs.
	 */
	private function save_setting() {
		if ( ! automator_filter_has_var( 'option_page', INPUT_POST ) ) {
			return null;
		}

		if ( ! automator_filter_has_var( '_wpnonce', INPUT_POST ) ) {
			return null;
		}

		$is_launcher_enabled = automator_filter_has_var( self::ENABLED_KEY, INPUT_POST )
			&& automator_filter_input( self::ENABLED_KEY, INPUT_POST, FILTER_VALIDATE_BOOLEAN );

		$is_top_bar_button_enabled = automator_filter_has_var( self::TOP_BAR_BUTTON_ENABLED_KEY, INPUT_POST )
			&& automator_filter_input( self::TOP_BAR_BUTTON_ENABLED_KEY, INPUT_POST, FILTER_VALIDATE_BOOLEAN );

		return $this->process_save_request(
			automator_filter_input( 'option_page', INPUT_POST ),
			automator_filter_input( '_wpnonce', INPUT_POST ),
			$is_launcher_enabled,
			$is_top_bar_button_enabled
		);
	}

	/**
	 * Validate and save one normalized request.
	 *
	 * @param string $option_page              Submitted option group.
	 * @param string $nonce                    Submitted nonce.
	 * @param bool   $is_launcher_enabled      Submitted launcher setting.
	 * @param bool   $is_top_bar_button_enabled Submitted top-bar setting.
	 *
	 * @return array{enabled: bool, top_bar_button_enabled: bool, saved: bool}|null Save result, or null when validation fails.
	 */
	private function process_save_request(
		string $option_page,
		string $nonce,
		bool $is_launcher_enabled,
		bool $is_top_bar_button_enabled
	) {
		if ( ! current_user_can( automator_get_admin_capability() ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined
			return null;
		}

		if ( self::SETTINGS_GROUP !== $option_page ) {
			return null;
		}

		if ( ! wp_verify_nonce( $nonce, self::SETTINGS_GROUP . '-options' ) ) {
			return null;
		}

		$settings = automator_get_option( self::OPTION_NAME, self::get_defaults() );

		if ( ! is_array( $settings ) ) {
			$settings = self::get_defaults();
		}

		$settings[ self::ENABLED_KEY ]                = $is_launcher_enabled;
		$settings[ self::TOP_BAR_BUTTON_ENABLED_KEY ] = $is_top_bar_button_enabled;

		if ( ! automator_update_option( self::OPTION_NAME, $settings ) ) {
			$persisted = self::get_settings( true );

			return array(
				self::ENABLED_KEY                => $persisted[ self::ENABLED_KEY ],
				self::TOP_BAR_BUTTON_ENABLED_KEY => $persisted[ self::TOP_BAR_BUTTON_ENABLED_KEY ],
				'saved'                          => false,
			);
		}

		return array(
			self::ENABLED_KEY                => $is_launcher_enabled,
			self::TOP_BAR_BUTTON_ENABLED_KEY => $is_top_bar_button_enabled,
			'saved'                          => true,
		);
	}
}

new Admin_Settings_Uncanny_Agent_General();
