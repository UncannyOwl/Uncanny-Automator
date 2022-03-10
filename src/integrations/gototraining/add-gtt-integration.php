<?php

namespace Uncanny_Automator;

/**
 * Class Add_Gtt_Integration
 * @package Uncanny_Automator
 */
class Add_Gtt_Integration {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'GTT';

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
			'gototraining',
			'helpers',
		);

		$pro_integration_helpers_path = ABSPATH . implode( DIRECTORY_SEPARATOR, $directories ) . '/gototraining-pro-helpers.php';
		
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
		$directory[] = dirname( __FILE__ ) . '/triggers';
		$directory[] = dirname( __FILE__ ) . '/tokens';

		return $directory;
	}

	/**
	 * Register the integration by pushing it into the global automator object
	 */
	public function add_integration_func() {

		// Check if consumer key and secret is available.
		$gtt_options = get_option( '_uncannyowl_gtt_settings', array() );

		Automator()->register->integration( self::$integration, array(
			'name'     => 'GoTo Training',
			'icon_svg' => Utilities::automator_get_integration_icon( __DIR__ . '/img/gototraining-icon.svg' ),
			'connected'    => isset( $gtt_options['refresh_token'] ) && ! empty( $gtt_options['refresh_token'] ),
			'settings_url' => automator_get_premium_integrations_settings_url( 'go-to-training' ),
		) );

	}
}
