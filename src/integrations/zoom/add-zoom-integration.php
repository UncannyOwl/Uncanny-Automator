<?php

namespace Uncanny_Automator;

/**
 * Class Add_Zoom_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Zoom_Integration {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'ZOOM';

	/**
	 * connected
	 *
	 * @var bool
	 */
	public $connected = false;

	/**
	 * Add_Integration constructor.
	 */
	public function __construct() {}

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
			'zoom',
			'helpers',
		);

		$pro_integration_helpers_path = ABSPATH . implode( DIRECTORY_SEPARATOR, $directories ) . '/zoom-pro-helpers.php';

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

		$zoom_client = get_option( '_uncannyowl_zoom_settings', array() );
		$user        = get_option( 'uap_zoom_api_connected_user', '' );

		if ( isset( $zoom_client['access_token'] ) && ! empty( $zoom_client['access_token'] ) && ! empty( $user ) ) {
			$this->connected = true;
		}

		Automator()->register->integration(
			self::$integration,
			array(
				'name'         => 'Zoom Meetings',
				'icon_svg'     => Utilities::automator_get_integration_icon( __DIR__ . '/img/zoom-icon.svg' ),
				'connected'    => $this->connected,
				'settings_url' => automator_get_premium_integrations_settings_url( 'zoom-api' ),
			)
		);

	}
}
