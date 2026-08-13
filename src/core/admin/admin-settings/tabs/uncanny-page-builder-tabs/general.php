<?php

namespace Uncanny_Automator;

use Uncanny_Automator\App\Infrastructure\Page_Builder\Page_Builder_Settings;

/**
 * Class Admin_Settings_Uncanny_Page_Builder_General
 *
 * @since   7.5
 * @version 1.0
 * @package Uncanny_Automator
 */
class Admin_Settings_Uncanny_Page_Builder_General {

	const SETTINGS_GROUP = 'uncanny_automator_uncanny_page_builder';
	const OPTION_NAME    = Page_Builder_Settings::OPTION_NAME;
	const ENABLED_KEY    = Page_Builder_Settings::ENABLED_KEY;

	/**
	 * Result from the current save request.
	 *
	 * @var array{enabled: bool, saved: bool}|null
	 */
	private $save_result = null;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'automator_settings_uncanny_page_builder_tabs', array( $this, 'register_tab' ), 10, 1 );
		add_action( 'admin_init', array( $this, 'maybe_save_setting' ) );
	}

	/**
	 * Return the default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return Page_Builder_Settings::defaults();
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key   Setting key.
	 * @param bool   $force Get the value from the database.
	 *
	 * @return mixed
	 */
	public static function get_setting( string $key, bool $force = false ) {
		if ( self::ENABLED_KEY === $key ) {
			return ( new Page_Builder_Settings() )->is_enabled( $force );
		}

		return self::get_defaults()[ $key ] ?? null;
	}

	/**
	 * Register the General subtab.
	 *
	 * @param array<string, object> $tabs Registered subtabs.
	 *
	 * @return array<string, object>
	 */
	public function register_tab( $tabs = null ) {
		$tabs = is_array( $tabs ) ? $tabs : array();

		try {
			$tabs['general'] = (object) array(
				'name'     => esc_html__( 'General', 'uncanny-automator' ),
				'function' => array( $this, 'tab_output' ),
				'preload'  => false,
				'icon'     => 'cog',
			);
		} catch ( \Throwable $throwable ) {
			error_log( sprintf( '[Uncanny Page Builder] General settings tab registration failed (%s).', get_class( $throwable ) ) );
		}

		return $tabs;
	}

	/**
	 * Output the General settings panel.
	 *
	 * @return void
	 */
	public function tab_output() {
		try {
			$is_enabled  = self::get_setting( self::ENABLED_KEY );
			$save_failed = false;
			$save_result = $this->save_result;

			if ( null !== $save_result ) {
				$is_enabled  = $save_result['enabled'];
				$save_failed = ! $save_result['saved'];
			}

			include Utilities::automator_get_view( 'admin-settings/tab/uncanny-page-builder/general.php' );
		} catch ( \Throwable $throwable ) {
			error_log( sprintf( '[Uncanny Page Builder] General settings output failed (%s).', get_class( $throwable ) ) );
		}
	}

	/**
	 * Save the setting and redirect successful requests to a fresh GET.
	 *
	 * @return void
	 */
	public function maybe_save_setting() {
		try {
			$this->save_result = $this->save_setting();

			if ( null === $this->save_result || ! $this->save_result['saved'] ) {
				return;
			}

			wp_safe_redirect( Admin_Settings::utility_get_settings_page_link( 'uncanny-page-builder' ) );
			exit;
		} catch ( \Throwable $throwable ) {
			error_log( sprintf( '[Uncanny Page Builder] General settings save failed (%s).', get_class( $throwable ) ) );
			return;
		}
	}

	/**
	 * Save the enable setting.
	 *
	 * @return array{enabled: bool, saved: bool}|null Save result, or null when no save occurs.
	 */
	private function save_setting() {
		if ( ! automator_filter_has_var( 'option_page', INPUT_POST ) ) {
			return null;
		}

		if ( ! automator_filter_has_var( '_wpnonce', INPUT_POST ) ) {
			return null;
		}

		$is_enabled = automator_filter_has_var( self::ENABLED_KEY, INPUT_POST )
			&& automator_filter_input( self::ENABLED_KEY, INPUT_POST, FILTER_VALIDATE_BOOLEAN );

		return $this->process_save_request(
			automator_filter_input( 'option_page', INPUT_POST ),
			automator_filter_input( '_wpnonce', INPUT_POST ),
			$is_enabled
		);
	}

	/**
	 * Validate and save one normalized request.
	 *
	 * @param string $option_page Submitted option group.
	 * @param string $nonce       Submitted nonce.
	 * @param bool   $is_enabled  Submitted setting.
	 *
	 * @return array{enabled: bool, saved: bool}|null Save result, or null when validation fails.
	 */
	private function process_save_request( string $option_page, string $nonce, bool $is_enabled ) {
		if ( ! current_user_can( automator_get_admin_capability() ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined
			return null;
		}

		if ( self::SETTINGS_GROUP !== $option_page ) {
			return null;
		}

		if ( ! wp_verify_nonce( $nonce, self::SETTINGS_GROUP . '-options' ) ) {
			return null;
		}

		$settings = new Page_Builder_Settings();

		if ( ! $settings->update_enabled( $is_enabled ) ) {
			return array(
				'enabled' => $settings->is_enabled( true ),
				'saved'   => false,
			);
		}

		return array(
			'enabled' => $is_enabled,
			'saved'   => true,
		);
	}
}

new Admin_Settings_Uncanny_Page_Builder_General();
