<?php

namespace Uncanny_Automator;

/**
 * Class Add_Mailchimp_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Mailchimp_Integration {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'MAILCHIMP';

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
			'mailchimp',
			'helpers',
		);

		$pro_integration_helpers_path = ABSPATH . implode( DIRECTORY_SEPARATOR, $directories ) . '/mailchimp-pro-helpers.php';

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
		$directory[] = dirname( __FILE__ ) . '/tokens';
		$directory[] = dirname( __FILE__ ) . '/actions';
		$directory[] = dirname( __FILE__ ) . '/triggers';

		return $directory;

	}

	/**
	 * Register the integration by pushing it into the global automator object
	 */
	public function add_integration_func() {

		global $uncanny_automator;

		// check if Consumer Key and Consumer Secret available
		$gtw_options = get_option( '_uncannyowl_mailchimp_settings', array() );

		if ( isset( $gtw_options['access_token'] ) && ! empty( $gtw_options['access_token'] ) ) {
			$this->connected = true;
		}

		$uncanny_automator->register->integration(
			self::$integration,
			array(
				'name'         => 'Mailchimp',
				'connected'    => $this->connected,
				'icon_svg'     => Utilities::automator_get_integration_icon( __DIR__ . '/img/mailchimp-icon.svg' ),
				'settings_url' => automator_get_premium_integrations_settings_url( 'mailchimp_api' ),
			)
		);

	}
}
