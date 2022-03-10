<?php

namespace Uncanny_Automator;

/**
 * Class Add_Slack_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Slack_Integration {
	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'SLACK';

	/**
	 * connected
	 *
	 * @var bool
	 */
	public $connected = false;

	/**
	 * Add_Integration constructor.
	 */
	public function __construct() {

	}

	/**
	 * This integration doesn't require any third-party plugins too be active.
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
			'slack',
			'helpers',
		);

		$pro_integration_helpers_path = ABSPATH . implode( DIRECTORY_SEPARATOR, $directories ) . '/slack-pro-helpers.php';

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

		global $uncanny_automator;

		$slack_client = get_option( '_uncannyowl_slack_settings', array() );

		if ( isset( $slack_client->access_token ) && ! empty( $slack_client->access_token ) ) {
			$this->connected = true;
		}

		$uncanny_automator->register->integration(
			self::$integration,
			array(
				'name'         => 'Slack',
				'icon_svg'     => Utilities::automator_get_integration_icon( __DIR__ . '/img/slack-icon.svg' ),
				'connected'    => $this->connected,
				'settings_url' => automator_get_premium_integrations_settings_url( 'slack_api' )
			)
		);

	}

}
