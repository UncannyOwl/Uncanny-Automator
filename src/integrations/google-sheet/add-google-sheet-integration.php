<?php

namespace Uncanny_Automator;

/**
 * Class Add_Google_Sheet_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Google_Sheet_Integration {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'GOOGLESHEET';

	public $is_connected = false;

	/**
	 * Add_Integration constructor.
	 */
	public function __construct() {

		$client = get_option( '_uncannyowl_google_sheet_settings', array() );

		if ( ! empty( $client['refresh_token'] ) ) {

			$this->is_connected = true;

		}

	}

	/**
	 * Only load this integration and its triggers and actions if the related plugin is active
	 *
	 * @param $status
	 * @param $code
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $code ) {

		$is_enabled = true;

		$directories = array(
			'wp-content',
			'plugins',
			'uncanny-automator-pro',
			'src',
			'integrations',
			'google-sheet',
			'helpers',
		);

		$pro_integration_helpers_path = ABSPATH . implode( DIRECTORY_SEPARATOR, $directories ) . '/google-sheet-pro-helpers.php';

		// If the helper file exists in pro it means, the pro version still contains the old helper file.
		if ( file_exists( $pro_integration_helpers_path ) && is_automator_pro_active() ) {

			$is_enabled = false;

		}

		return $is_enabled;

	}

	/**
	 * Set the directories that the auto loader will run in
	 *
	 * @param $directory
	 *
	 * @return array
	 */
	public function add_integration_directory_func( $directory ) {

		$directory[] = dirname( __FILE__ ) . '/helpers';
		$directory[] = dirname( __FILE__ ) . '/actions';

		return $directory;
	}

	/**
	 * Register the integration by pushing it into the global automator object
	 */
	public function add_integration_func() {

		Automator()->register->integration(
			self::$integration,
			array(
				'name'         => 'Google Sheets',
				'connected'    => $this->is_connected && ! $this->has_missing_scope(),
				'icon_svg'     => Utilities::automator_get_integration_icon( __DIR__ . '/img/google-sheet-icon.svg' ),
				'settings_url' => automator_get_premium_integrations_settings_url( 'google-sheet' ),
			)
		);

	}

	/**
	 * Method has_missing_scope
	 *
	 * Checks the client if it has any missing scope or not.
	 *
	 * @return boolean True if there is a missing scope. Otherwise, false.
	 */
	public function has_missing_scope() {

		$client = get_option( '_uncannyowl_google_sheet_settings', array() );

		$scopes = array(
			'https://www.googleapis.com/auth/drive',
			'https://www.googleapis.com/auth/spreadsheets',
			'https://www.googleapis.com/auth/userinfo.profile',
			'https://www.googleapis.com/auth/userinfo.email',
		);

		if ( empty( $client['scope'] ) ) {
			return true;
		}

		$has_missing_scope = false;

		foreach ( $scopes as $scope ) {
			if ( false === strpos( $client['scope'], $scope ) ) {
				$has_missing_scope = true;
			}
		}

		return $has_missing_scope;
	}
}
